<?php

namespace App\Actions\Servers;

use App\Enums\Currency;
use App\Enums\ServerType;
use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Support\Servers\ServerApiClient;
use Carbon\CarbonInterface;

class PullFromServer
{
    public function __construct(
        private ImportServerPackages $importServerPackages,
        private ImportServerAccounts $importServerAccounts,
    ) {}

    /**
     * Read the server's packages and accounts, then import both: packages first, so WHM accounts
     * find their catalog service. Both reads happen before anything is written, so a server
     * failure leaves no half import. Never throws for server failures.
     *
     * @param  array<string, array{price: int, currency: Currency}>  $newPackagePrices  WHM: price per new package name.
     * @param  CarbonInterface|null  $cpanelStartDate  cPanel: start date for the domains.
     */
    public function handle(Server $server, array $newPackagePrices = [], ?CarbonInterface $cpanelStartDate = null): ServerPullResult
    {
        $api = ServerApiClient::for($server);

        try {
            $packages = $server->type === ServerType::Whm ? $api->packages() : [];
            $accounts = $api->accounts();
        } catch (RemoteServerError $error) {
            return new ServerPullResult(error: $error->getMessage());
        }

        $packageResult = $server->type === ServerType::Whm
            ? $this->importServerPackages->handle($server, $packages, $newPackagePrices)
            : null;

        $accountResult = $this->importServerAccounts->handle($server, $accounts, $cpanelStartDate);

        return new ServerPullResult(null, $packageResult, $accountResult);
    }
}
