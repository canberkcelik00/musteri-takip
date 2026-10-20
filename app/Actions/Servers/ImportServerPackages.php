<?php

namespace App\Actions\Servers;

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Server;
use App\Support\Servers\RemotePackage;
use App\Support\Servers\WhmPackageDetails;
use Illuminate\Support\Facades\DB;

class ImportServerPackages
{
    /**
     * Turn a server's packages into hosting catalog services, matched by server and package name.
     * A new package needs a price in `$newPackagePrices` (keyed by package name) and is skipped
     * without one; an existing package only gets its `details` refreshed, never its name or price.
     *
     * @param  iterable<RemotePackage>  $packages
     * @param  array<string, array{price: int, currency: Currency}>  $newPackagePrices
     */
    public function handle(Server $server, iterable $packages, array $newPackagePrices = []): PackageImportResult
    {
        $created = $updated = $skipped = [];

        DB::transaction(function () use ($server, $packages, $newPackagePrices, &$created, &$updated, &$skipped): void {
            foreach ($packages as $package) {
                $details = WhmPackageDetails::for($package, $server);

                $existing = CatalogService::query()
                    ->where('server_id', $server->id)
                    ->where('remote_package', $package->name)
                    ->first();

                if ($existing !== null) {
                    $existing->update(['details' => $details]);
                    $updated[] = $package->name;

                    continue;
                }

                $given = $newPackagePrices[$package->name] ?? null;

                if ($given === null || $given['price'] <= 0) {
                    $skipped[] = $package->name;

                    continue;
                }

                CatalogService::query()->create([
                    'server_id' => $server->id,
                    'remote_package' => $package->name,
                    'type' => ServiceType::Hosting,
                    'name' => $package->name,
                    'price' => $given['price'],
                    'currency' => $given['currency'],
                    'details' => $details,
                ]);
                $created[] = $package->name;
            }
        });

        return new PackageImportResult($created, $updated, $skipped);
    }
}
