<?php

namespace App\Actions\Servers;

use App\Enums\AccountMatchKind;
use App\Enums\RemoteServerErrorReason;
use App\Enums\ServerType;
use App\Exceptions\RemoteServerError;
use App\Models\CatalogService;
use App\Models\Server;
use App\Support\Servers\RemoteAccount;
use App\Support\Servers\RemotePackage;
use App\Support\Servers\RemoteServerApi;
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
        $rows = [];
        $domainsReadable = true;

        foreach ($accounts as $account) {
            if ($account->suspended) {
                $suspended++;

                continue;
            }

            switch ($this->matchServerAccount->handle($server, $account)->kind) {
                case AccountMatchKind::New:
                    $new++;
                    $domains = $domainsReadable ? $this->domainsOf($api, $account, $domainsReadable) : [$account->domain];
                    $rows[] = ['key' => $account->key, 'username' => $account->username, 'domain' => $account->domain, 'domains' => $domains];
                    break;
                case AccountMatchKind::Link:
                    $linkable++;
                    break;
                case AccountMatchKind::AlreadyLinked:
                    $linked++;
                    break;
            }
        }

        return new ServerPullPreview(null, $newPackages, $new, $linkable, $linked, $suspended, $rows);
    }

    /**
     * The account's domains, main domain first. A server failure falls back to the main domain
     * alone, and a timeout or lost connection stops asking for the remaining accounts.
     *
     * @return list<string>
     */
    private function domainsOf(RemoteServerApi $api, RemoteAccount $account, bool &$readable): array
    {
        try {
            $domains = $api->domainsOf($account);
        } catch (RemoteServerError $error) {
            $readable = ! in_array($error->reason, [RemoteServerErrorReason::Timeout, RemoteServerErrorReason::ConnectionFailed], true);

            return [$account->domain];
        }

        return in_array($account->domain, $domains, true) ? $domains : [$account->domain, ...$domains];
    }
}
