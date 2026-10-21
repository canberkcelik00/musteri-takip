<?php

namespace App\Actions\Servers;

use App\Exceptions\RemoteServerError;
use App\Models\AccountUsage;
use App\Models\Mailbox;
use App\Models\Server;
use App\Support\Servers\RemoteAccount;
use App\Support\Servers\RemoteAccountUsage;
use App\Support\Servers\ServerApiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read the usage of one hosting account from its server and store it (docs/DECISIONS.md T-112).
 * Never throws for a server problem: a failed read keeps the earlier values and records when and
 * why it failed, so callers read `failed_at` / `failure_reason` of the returned row.
 */
class RefreshAccountUsage
{
    public function handle(Server $server, string $username): AccountUsage
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();
        $account = new RemoteAccount($username, '', $username, null, null, null, false);

        try {
            $usage = ServerApiClient::for($server)->usageOf($account);
        } catch (RemoteServerError $error) {
            return AccountUsage::query()->updateOrCreate(
                ['server_id' => $server->getKey(), 'remote_username' => $username],
                ['failed_at' => $now, 'failure_reason' => $error->reason],
            );
        }

        return DB::transaction(fn (): AccountUsage => $this->store($server, $username, $usage, $now));
    }

    private function store(Server $server, string $username, RemoteAccountUsage $usage, Carbon $now): AccountUsage
    {
        $keys = ['server_id' => $server->getKey(), 'remote_username' => $username];

        $row = AccountUsage::query()->updateOrCreate($keys, [
            'resources' => array_map(fn ($resource): array => [
                'id' => $resource->id,
                'label' => $resource->label,
                'used' => $resource->used,
                'limit' => $resource->limit,
                'is_bytes' => $resource->isBytes,
            ], $usage->resources),
            'databases' => array_map(fn ($database): array => ['name' => $database->name, 'bytes' => $database->bytes], $usage->databases),
            'refreshed_at' => $now,
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        foreach ($usage->mailboxes as $mailbox) {
            Mailbox::query()->updateOrCreate([...$keys, 'address' => $mailbox->address], [
                'domain' => $mailbox->domain,
                'used_bytes' => $mailbox->usedBytes,
                'quota_bytes' => $mailbox->quotaBytes,
                'login_suspended' => $mailbox->loginSuspended,
                'incoming_suspended' => $mailbox->incomingSuspended,
            ]);
        }

        Mailbox::query()
            ->where($keys)
            ->whereNotIn('address', array_map(fn ($mailbox): string => $mailbox->address, $usage->mailboxes))
            ->delete();

        return $row;
    }
}
