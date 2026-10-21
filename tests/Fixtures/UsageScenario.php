<?php

namespace Tests\Fixtures;

use App\Enums\ServiceType;
use App\Models\AccountUsage;
use App\Models\CatalogService;
use App\Models\CustomerService;
use App\Models\Mailbox;
use App\Models\Server;

/**
 * Builds the records the usage warnings read: a customer service linked to a server account, the
 * stored usage of that account, and its mailboxes.
 */
final class UsageScenario
{
    private const int GIGABYTE = 1024 * 1024 * 1024;

    /**
     * A customer service whose first item is linked to the account `$key` of the server.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function linkedService(Server $server, string $key, array $attributes = [], ?string $domain = null): CustomerService
    {
        $service = CustomerService::factory()
            ->forCatalogService(CatalogService::factory()->ofType(ServiceType::Hosting)->create())
            ->create($attributes);
        $item = $service->items()->firstOrFail();
        $item->forceFill([
            'server_id' => $server->id,
            'remote_key' => $key,
            'details' => ['domain' => $domain ?? $key],
        ])->save();

        return $service;
    }

    /**
     * @return array{id: string, label: string, used: int, limit: int|null, is_bytes: bool}
     */
    public static function resource(string $id, int $used, ?int $limit, bool $isBytes = false): array
    {
        return ['id' => $id, 'label' => ucfirst($id), 'used' => $used, 'limit' => $limit, 'is_bytes' => $isBytes];
    }

    /**
     * Stored usage with a disk at 94 % of 5 GB unless other resources are given.
     *
     * @param  list<array{id: string, label: string, used: int, limit: int|null, is_bytes: bool}>|null  $resources
     * @param  array<string, mixed>  $attributes
     */
    public static function usage(Server $server, string $username, ?array $resources = null, array $attributes = []): AccountUsage
    {
        return AccountUsage::factory()->create([
            'server_id' => $server->id,
            'remote_username' => $username,
            'resources' => $resources ?? [self::resource('disk_usage', (int) ceil(self::GIGABYTE * 4.7), 5 * self::GIGABYTE, true)],
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function mailbox(Server $server, string $username, string $address, int $usedBytes, ?int $quotaBytes, array $attributes = []): Mailbox
    {
        return Mailbox::factory()->create([
            'server_id' => $server->id,
            'remote_username' => $username,
            'address' => $address,
            'domain' => substr((string) strrchr($address, '@'), 1),
            'used_bytes' => $usedBytes,
            'quota_bytes' => $quotaBytes,
            ...$attributes,
        ]);
    }
}
