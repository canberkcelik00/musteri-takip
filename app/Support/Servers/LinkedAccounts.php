<?php

namespace App\Support\Servers;

use App\Enums\CustomerServiceStatus;
use App\Enums\ServerType;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The hosting accounts whose usage matters: those a customer service item is linked to
 * (`server_id` + `remote_key`), while its service is active and neither its customer nor its
 * server is archived. The one place that says which items, and which account of the server each
 * one belongs to; the refresh and the warnings both read it.
 */
final class LinkedAccounts
{
    /**
     * @return Collection<int, CustomerServiceItem> with `server` and `customerService.customer` loaded
     */
    public static function items(): Collection
    {
        return CustomerServiceItem::query()
            ->whereNotNull('server_id')
            ->whereNotNull('remote_key')
            ->whereHas('server', fn (Builder $query): Builder => $query->notArchived())
            ->whereHas('customerService', fn (Builder $query): Builder => $query
                ->where('status', CustomerServiceStatus::Active)
                ->whereHas('customer', fn (Builder $query): Builder => $query->notArchived()))
            ->with(['server', 'customerService.customer'])
            ->get();
    }

    /**
     * The server user the item's account is stored under: a WHM account's cPanel user is the
     * item's `remote_key`; every domain of a cPanel connection shares the connection's own user.
     */
    public static function usernameOf(CustomerServiceItem $item): string
    {
        $server = $item->server;
        assert($server instanceof Server);

        return $server->type === ServerType::Whm ? (string) $item->remote_key : $server->username;
    }

    /**
     * Key of an account across servers: "serverId|username".
     */
    public static function keyOf(int $serverId, string $username): string
    {
        return "{$serverId}|{$username}";
    }
}
