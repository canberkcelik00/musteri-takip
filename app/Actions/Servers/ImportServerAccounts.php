<?php

namespace App\Actions\Servers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\CustomerServices\AssignCustomerService;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Support\Servers\AnniversaryStart;
use App\Support\Servers\RemoteAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportServerAccounts
{
    public function __construct(
        private CreateCustomer $createCustomer,
        private AssignCustomerService $assignCustomerService,
    ) {}

    /**
     * Turn a server's accounts into customers and customer services, one transaction per account,
     * so a failing account is reported by its key and the others still go through. Matching is by
     * `server_id` + `remote_key`; a linked account is left alone, an unlinked active hosting item
     * with the same domain (WHM: or cPanel username) is linked instead of duplicated, and a short
     * name that already exists gets the service added. Suspended accounts are skipped. No charge
     * is created: the period starts on the anniversary covering today, so renewal runs as usual.
     * Linked items the server no longer lists are only reported.
     *
     * @param  iterable<RemoteAccount>  $accounts
     * @param  CarbonInterface|null  $cpanelStartDate  Start date for accounts without one (cPanel domains).
     */
    public function handle(Server $server, iterable $accounts, ?CarbonInterface $cpanelStartDate = null): AccountImportResult
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        $customersCreated = 0;
        $created = $linked = $alreadyLinked = $suspendedSkipped = $seen = [];
        $errors = [];

        foreach ($accounts as $account) {
            $seen[] = $account->key;

            if ($account->suspended) {
                $suspendedSkipped[] = $account->key;

                continue;
            }

            $startDate = AnniversaryStart::covering($account->startDate ?? $cpanelStartDate ?? $today, $today);

            try {
                [$outcome, $customerCreated] = DB::transaction(
                    fn (): array => $this->importAccount($server, $account, $startDate),
                );
            } catch (DomainException $e) {
                $errors[$account->key] = $e->getMessage();

                continue;
            } catch (Throwable $e) {
                report($e);
                $errors[$account->key] = __('servers.import_failed');

                continue;
            }

            $customersCreated += $customerCreated ? 1 : 0;

            match ($outcome) {
                'created' => $created[] = $account->key,
                'linked' => $linked[] = $account->key,
                default => $alreadyLinked[] = $account->key,
            };
        }

        $missingOnServer = $server->customerServiceItems()
            ->whereNotIn('remote_key', $seen)
            ->pluck('remote_key')
            ->all();

        $server->forceFill(['last_pulled_at' => now()])->save();

        return new AccountImportResult($customersCreated, $created, $linked, $alreadyLinked, $suspendedSkipped, $missingOnServer, $errors);
    }

    /**
     * @return array{0: 'created'|'linked'|'alreadyLinked', 1: bool} The outcome and whether a customer was created.
     */
    private function importAccount(Server $server, RemoteAccount $account, CarbonInterface $startDate): array
    {
        $isLinked = $server->customerServiceItems()->where('remote_key', $account->key)->exists();

        if ($isLinked) {
            return ['alreadyLinked', false];
        }

        $details = [
            'domain' => $account->domain,
            'server' => $server->name,
            'cpanel_username' => $account->username,
        ];

        $existing = $this->unlinkedHostingItem($server, $account);

        if ($existing !== null) {
            $this->link($existing, $server, $account, $details);

            return ['linked', false];
        }

        $catalogService = $this->catalogServiceFor($server, $account);

        $customer = Customer::query()->where('short_name', $account->domain)->first();
        $customerCreated = $customer === null;

        $customer ??= $this->createCustomer->handle(
            ['short_name' => $account->domain],
            ['name' => $account->domain, 'email' => $account->email ?: null],
        );

        $customerService = $this->assignCustomerService->handle($customer, $catalogService, [
            'start_date' => $startDate,
            'create_first_charge' => false,
            'items' => [$catalogService->id => ['details' => $details]],
        ]);

        $this->link($customerService->items()->sole(), $server, $account, $details);

        return ['created', $customerCreated];
    }

    private function unlinkedHostingItem(Server $server, RemoteAccount $account): ?CustomerServiceItem
    {
        return CustomerServiceItem::query()
            ->where('type', ServiceType::Hosting)
            ->whereNull('server_id')
            ->whereHas('customerService', fn (Builder $query): Builder => $query->where('status', CustomerServiceStatus::Active))
            ->where(function (Builder $query) use ($server, $account): void {
                $query->whereRaw($this->detailEquals('domain'), [$account->domain]);

                if ($server->type === ServerType::Whm) {
                    $query->orWhereRaw($this->detailEquals('cpanel_username'), [$account->username]);
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function detailEquals(string $key): string
    {
        return "JSON_UNQUOTE(JSON_EXTRACT(details, '$.{$key}')) COLLATE utf8mb4_unicode_ci = ?";
    }

    /**
     * Connect the item to the account and fill the hosting details it lacks; values the user
     * entered are kept.
     *
     * @param  array{domain: string, server: string, cpanel_username: string}  $details
     */
    private function link(CustomerServiceItem $item, Server $server, RemoteAccount $account, array $details): void
    {
        $current = $item->details ?? [];

        foreach ($details as $key => $value) {
            if (blank($current[$key] ?? null)) {
                $current[$key] = $value;
            }
        }

        $item->forceFill(['server_id' => $server->id, 'remote_key' => $account->key, 'details' => $current])->save();
    }

    private function catalogServiceFor(Server $server, RemoteAccount $account): CatalogService
    {
        if ($server->type === ServerType::Cpanel) {
            return $server->catalogService ?? throw new DomainException(__('servers.import_server_catalog_missing'));
        }

        return CatalogService::query()
            ->where('server_id', $server->id)
            ->where('remote_package', $account->packageName)
            ->first()
            ?? throw new DomainException(__('servers.import_package_missing', ['package' => (string) $account->packageName]));
    }
}
