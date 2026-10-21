<?php

namespace App\Support\Servers;

use App\Exceptions\RemoteServerError;

interface RemoteServerApi
{
    /**
     * Make one read-only request that needs a valid token.
     *
     * @throws RemoteServerError
     */
    public function ping(): void;

    /**
     * @return list<RemotePackage>
     *
     * @throws RemoteServerError
     */
    public function packages(): array;

    /**
     * @return list<RemoteAccount>
     *
     * @throws RemoteServerError
     */
    public function accounts(): array;

    /**
     * Every domain of the account, main domain first: main, addon, parked and sub domains.
     *
     * @return list<string>
     *
     * @throws RemoteServerError
     */
    public function domainsOf(RemoteAccount $account): array;

    /**
     * The use of the account: metered resources (disk, bandwidth, counts), e-mail addresses with
     * their quotas, and databases. Read-only.
     *
     * @throws RemoteServerError
     */
    public function usageOf(RemoteAccount $account): RemoteAccountUsage;

    /**
     * The totals of the whole reseller (WHM only; a cPanel connection is a single account).
     *
     * @throws RemoteServerError
     */
    public function resellerTotals(): RemoteResellerTotals;
}
