<?php

namespace App\Mcp\Tools;

use App\Actions\Servers\BuildUsageReport;
use App\Actions\Servers\UsageMailbox;
use App\Actions\Servers\UsageMeter;
use App\Actions\Servers\UsageReport;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerServiceItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The hosting usage of one customer for Claude through BuildUsageReport, as the "Kullanım" window shows it: what the
 * last refresh stored, never a call to the server. Needs services.view, like the window and the quota warnings. The
 * server key, the license key and every password stay out; an account is named by domain, server and account user.
 */
#[Description('Hosting usage of one customer by id (from search-customers), as last stored by the usage refresh (not live): per linked hosting account the domain, server, disk, monthly traffic, file count, mailbox and database counters, each mailbox with its quota and each database size. refreshed_at tells how old the numbers are; failed_at is set when the last refresh failed.')]
#[IsReadOnly]
#[IsIdempotent]
class CustomerUsage extends BaseTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('The customer id.')->required(),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::ServicesView;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail($input['customer_id']);

        $items = CustomerServiceItem::query()
            ->whereHas('customerService', fn ($services) => $services->where('customer_id', $customer->id))
            ->whereNotNull('server_id')
            ->whereNotNull('remote_key')
            ->with(['server', 'customerService'])
            ->orderBy('id')
            ->get();

        $reports = app(BuildUsageReport::class)->handleMany($items);

        return Response::structured([
            'customer_id' => $customer->id,
            'short_name' => $customer->short_name,
            'total' => count($reports),
            'accounts' => $items
                ->filter(fn (CustomerServiceItem $item): bool => isset($reports[$item->getKey()]))
                ->take(self::MAX_ROWS)
                ->map(fn (CustomerServiceItem $item): array => $this->account($item, $reports[$item->getKey()]))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(CustomerServiceItem $item, UsageReport $report): array
    {
        return [
            'service_id' => $item->customer_service_id,
            'service' => $item->customerService->name,
            'domain' => $report->title,
            'server' => $report->serverName,
            'username' => $report->username,
            'has_data' => $report->hasData(),
            'refreshed_at' => $report->refreshedAt?->toDateTimeString(),
            'failed_at' => $report->failedAt?->toDateTimeString(),
            'failure_reason' => $report->failureReason?->value,
            'meters' => array_map(fn (UsageMeter $meter): array => self::meter($meter), [...$report->gauges, ...$report->counters]),
            'mailboxes' => array_map(fn (UsageMailbox $mailbox): array => [
                'address' => $mailbox->address,
                'login_suspended' => $mailbox->loginSuspended,
                'incoming_suspended' => $mailbox->incomingSuspended,
                ...self::meter($mailbox->meter),
            ], array_slice($report->mailboxes, 0, self::MAX_ROWS)),
            'databases' => array_map(fn (UsageMeter $database): array => [
                'name' => $database->label,
                'used' => $database->used,
                'text' => $database->usedText(),
            ], array_slice($report->databases, 0, self::MAX_ROWS)),
        ];
    }

    /**
     * @return array{id: string, label: string, used: int, limit: int|null, percent: int|null, level: string|null, is_bytes: bool, text: string}
     */
    private static function meter(UsageMeter $meter): array
    {
        return [
            'id' => $meter->id,
            'label' => $meter->label,
            'used' => $meter->used,
            'limit' => $meter->limit,
            'percent' => $meter->percent(),
            'level' => $meter->level?->value,
            'is_bytes' => $meter->isBytes,
            'text' => $meter->usageText(),
        ];
    }
}
