<?php

namespace App\Actions\Servers;

use App\Enums\ServerType;
use App\Enums\UsageLevel;
use App\Models\AccountUsage;
use App\Models\CustomerServiceItem;
use App\Models\Mailbox;
use App\Support\Servers\LinkedAccounts;
use App\Support\Servers\ResourceLabel;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Every stored hosting resource that is at or above the warning threshold right now, for linked
 * accounts of active services only. Stateless: the digest and "Bugün" list what is over the
 * threshold, the warning e-mail picks from it what is not yet announced. Fullest first.
 *
 * Resources without a limit never alert, and neither do the `lve*` resources: CloudLinux reports
 * momentary CPU, memory and process figures there, which are not quotas.
 */
class ListUsageAlerts
{
    private const string MOMENTARY_RESOURCE_PREFIX = 'lve';

    /**
     * Whether a resource is a momentary figure (CloudLinux `lve*`) rather than a quota.
     */
    public static function isMomentary(string $resourceId): bool
    {
        return str_starts_with($resourceId, self::MOMENTARY_RESOURCE_PREFIX);
    }

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return Collection<int, UsageAlert>
     */
    public function handle(): Collection
    {
        $threshold = $this->settings->usageWarningPercent();
        $itemsByAccount = LinkedAccounts::items()->groupBy(
            fn (CustomerServiceItem $item): string => LinkedAccounts::keyOf($item->server_id, LinkedAccounts::usernameOf($item)),
        );
        $accounts = AccountUsage::query()->whereNotNull('refreshed_at')->get();
        $alerts = [];

        foreach ($accounts as $usage) {
            $items = $itemsByAccount->get(LinkedAccounts::keyOf($usage->server_id, $usage->remote_username));

            if ($items === null) {
                continue;
            }

            foreach ($usage->resources ?? [] as $resource) {
                $level = self::isMomentary($resource['id'])
                    ? null
                    : UsageLevel::of($resource['used'], $resource['limit'], $threshold);

                if ($level === null) {
                    continue;
                }

                $alerts[] = new UsageAlert(
                    accountUsageId: $usage->id,
                    resourceId: $resource['id'],
                    mailboxId: null,
                    subject: ResourceLabel::for($resource['id'], $resource['label']),
                    used: $resource['used'],
                    limit: (int) $resource['limit'],
                    isBytes: $resource['is_bytes'],
                    level: $level,
                    warnedLevel: UsageLevel::tryFrom((string) ($usage->warned_levels[$resource['id']] ?? '')),
                    owners: $this->owners($items),
                );
            }
        }

        $accountIds = $accounts->mapWithKeys(fn (AccountUsage $usage): array => [LinkedAccounts::keyOf($usage->server_id, $usage->remote_username) => $usage->id]);

        foreach (Mailbox::query()->whereNotNull('quota_bytes')->get() as $mailbox) {
            $key = LinkedAccounts::keyOf($mailbox->server_id, $mailbox->remote_username);
            $items = $itemsByAccount->get($key);
            $level = UsageLevel::of($mailbox->used_bytes, $mailbox->quota_bytes, $threshold);

            if ($items === null || $level === null || ! $accountIds->has($key)) {
                continue;
            }

            $alerts[] = new UsageAlert(
                accountUsageId: $accountIds->get($key),
                resourceId: null,
                mailboxId: $mailbox->id,
                subject: $mailbox->address,
                used: $mailbox->used_bytes,
                limit: (int) $mailbox->quota_bytes,
                isBytes: true,
                level: $level,
                warnedLevel: $mailbox->warned_level,
                owners: $this->owners($this->ownersOfMailbox($items, $mailbox)),
            );
        }

        return collect($alerts)
            ->sort(fn (UsageAlert $a, UsageAlert $b): int => [$b->level->severity(), $b->percent(), $a->subject] <=> [$a->level->severity(), $a->percent(), $b->subject])
            ->values();
    }

    /**
     * A cPanel mailbox belongs to the item of its own domain; any other mailbox (or a domain
     * that has no item) to every item of the account.
     *
     * @param  Collection<int, CustomerServiceItem>  $items
     * @return Collection<int, CustomerServiceItem>
     */
    private function ownersOfMailbox(Collection $items, Mailbox $mailbox): Collection
    {
        $ofDomain = $items->filter(fn (CustomerServiceItem $item): bool => $item->server?->type === ServerType::Cpanel && $item->remote_key === $mailbox->domain);

        return $ofDomain->isNotEmpty() ? $ofDomain : $items;
    }

    /**
     * @param  Collection<int, CustomerServiceItem>  $items
     * @return list<array{item_id: int, customer_id: int, short_name: string, domain: string|null}>
     */
    private function owners(Collection $items): array
    {
        return $items
            ->map(fn (CustomerServiceItem $item): array => [
                'item_id' => $item->getKey(),
                'customer_id' => $item->customerService->customer_id,
                'short_name' => $item->customerService->customer->short_name,
                'domain' => $item->details['domain'] ?? $item->remote_key,
            ])
            ->unique(fn (array $owner): string => $owner['customer_id'].'|'.$owner['domain'])
            ->values()
            ->all();
    }
}
