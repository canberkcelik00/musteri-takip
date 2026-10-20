<?php

namespace App\Actions\Servers;

use App\Enums\AccountMatchKind;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Support\Servers\RemoteAccount;
use Illuminate\Database\Eloquent\Builder;

class MatchServerAccount
{
    /**
     * Where a server account stands on our side: an item already linked to it, an unlinked active
     * hosting item with the same domain (WHM: or cPanel username) to link, or nothing (new).
     * Read-only; the import and its preview share this so they always agree.
     */
    public function handle(Server $server, RemoteAccount $account): AccountMatch
    {
        $isLinked = $server->customerServiceItems()->where('remote_key', $account->key)->exists();

        if ($isLinked) {
            return new AccountMatch(AccountMatchKind::AlreadyLinked);
        }

        $item = $this->unlinkedHostingItem($server, $account);

        return $item === null
            ? new AccountMatch(AccountMatchKind::New)
            : new AccountMatch(AccountMatchKind::Link, $item);
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
}
