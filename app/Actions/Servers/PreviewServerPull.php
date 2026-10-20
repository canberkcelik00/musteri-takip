<?php

namespace App\Actions\Servers;

use App\Enums\AccountMatchKind;
use App\Enums\ServerType;
use App\Exceptions\RemoteServerError;
use App\Models\CatalogService;
use App\Models\Server;
use App\Support\Servers\RemotePackage;
use App\Support\Servers\ServerApiClient;

class PreviewServerPull
{
    public function __construct(private MatchServerAccount $matchServerAccount) {}

    /**
     * Read the server and count what a pull would create or connect. Read-only; never throws:
     * a server failure becomes the preview's Turkish `error`.
     */
    public function handle(Server $server): ServerPullPreview
    {
        $api = ServerApiClient::for($server);

        try {
            $packages = $server->type === ServerType::Whm ? $api->packages() : [];
            $accounts = $api->accounts();
        } catch (RemoteServerError $error) {
            return new ServerPullPreview(error: $error->getMessage());
        }

        $known = CatalogService::query()->where('server_id', $server->id)->pluck('remote_package')->all();

        $newPackages = collect($packages)
            ->map(fn (RemotePackage $package): string => $package->name)
            ->reject(fn (string $name): bool => in_array($name, $known, true))
            ->values()
            ->all();

        $new = $linkable = $linked = $suspended = 0;

        foreach ($accounts as $account) {
            if ($account->suspended) {
                $suspended++;

                continue;
            }

            match ($this->matchServerAccount->handle($server, $account)->kind) {
                AccountMatchKind::New => $new++,
                AccountMatchKind::Link => $linkable++,
                AccountMatchKind::AlreadyLinked => $linked++,
            };
        }

        return new ServerPullPreview(null, $newPackages, $new, $linkable, $linked, $suspended);
    }
}
