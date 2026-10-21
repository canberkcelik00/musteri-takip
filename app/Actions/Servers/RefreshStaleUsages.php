<?php

namespace App\Actions\Servers;

use App\Enums\CustomerServiceStatus;
use App\Enums\ServerType;
use App\Models\AccountUsage;
use App\Models\CustomerServiceItem;
use App\Models\Mailbox;
use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Refresh the usage of every hosting account that has a linked, active service and was not asked
 * for six hours ("henüz yapılmadıysa yap"; docs/DECISIONS.md T-112). Called on every scheduler
 * run. Accounts never asked come first, then the oldest attempt; a failed attempt counts as an
 * attempt, so a broken server is retried after the interval, not on every run.
 *
 * A run stops once its time budget is used up (after the account in progress) and the rest
 * waits for the next run. One account failing does not stop the others. Cache rows of accounts
 * no linked, active service needs any more are removed.
 *
 * An account is a WHM account (the item's `remote_key` is its cPanel user) or, on a cPanel
 * connection, the connection's own user shared by all its domains.
 */
class RefreshStaleUsages
{
    private const int REFRESH_AFTER_HOURS = 6;

    public function __construct(private readonly RefreshAccountUsage $refreshAccountUsage) {}

    public function handle(int $budgetSeconds = 60): UsageRefreshResult
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();
        $targets = $this->targets();
        $removed = $this->removeUnneeded($targets);

        $due = $this->due($targets, $now);
        $startedAt = hrtime(true);
        $refreshed = 0;
        $failed = 0;
        $remaining = 0;

        foreach ($due as $position => [$server, $username]) {
            $usage = $this->refreshAccountUsage->handle($server, $username);
            $usage->failure_reason === null ? $refreshed++ : $failed++;

            if ((hrtime(true) - $startedAt) / 1e9 >= $budgetSeconds) {
                $remaining = $due->count() - $position - 1;

                break;
            }
        }

        return new UsageRefreshResult($refreshed, $failed, $remaining, $removed);
    }

    /**
     * Every account that needs its usage, keyed by "serverId|username".
     *
     * @return Collection<non-falsy-string, array{0: Server, 1: string}>
     */
    private function targets(): Collection
    {
        return CustomerServiceItem::query()
            ->whereNotNull('server_id')
            ->whereNotNull('remote_key')
            ->whereHas('server', fn (Builder $query): Builder => $query->notArchived())
            ->whereHas('customerService', fn (Builder $query): Builder => $query
                ->where('status', CustomerServiceStatus::Active)
                ->whereHas('customer', fn (Builder $query): Builder => $query->notArchived()))
            ->with('server')
            ->get()
            ->mapWithKeys(function (CustomerServiceItem $item): array {
                $server = $item->server;
                assert($server instanceof Server);

                $username = $server->type === ServerType::Whm ? (string) $item->remote_key : $server->username;

                return ["{$server->getKey()}|{$username}" => [$server, $username]];
            });
    }

    /**
     * @param  Collection<non-falsy-string, array{0: Server, 1: string}>  $targets
     * @return Collection<int, array{0: Server, 1: string}>
     */
    private function due(Collection $targets, Carbon $now): Collection
    {
        $threshold = $now->copy()->subHours(self::REFRESH_AFTER_HOURS);

        $attempts = AccountUsage::query()
            ->whereIn('server_id', $targets->map(fn (array $target): int => $target[0]->getKey())->unique()->all())
            ->get()
            ->mapWithKeys(fn (AccountUsage $usage): array => ["{$usage->server_id}|{$usage->remote_username}" => $usage->lastAttemptAt()]);

        return $targets
            ->filter(fn ($target, string $key): bool => ! $attempts->get($key) || $attempts->get($key)->lessThanOrEqualTo($threshold))
            ->sortBy(fn ($target, string $key): int => $attempts->get($key)?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @param  Collection<non-falsy-string, array{0: Server, 1: string}>  $targets
     */
    private function removeUnneeded(Collection $targets): int
    {
        $removed = 0;

        foreach ([AccountUsage::class, Mailbox::class] as $model) {
            $model::query()->get(['id', 'server_id', 'remote_username'])
                ->reject(fn ($row): bool => $targets->has("{$row->server_id}|{$row->remote_username}"))
                ->each(function ($row) use (&$removed): void {
                    $row->delete();
                    $removed++;
                });
        }

        return $removed;
    }
}
