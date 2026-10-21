<?php

namespace Database\Factories;

use App\Enums\RemoteServerErrorReason;
use App\Models\Server;
use App\Models\ServerUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerUsage>
 */
class ServerUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory()->whm(),
            'resources' => [
                ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => 40802189312, 'limit' => 53687091200, 'is_bytes' => true, 'limit_read' => true],
                ['id' => 'reseller_bandwidth', 'label' => 'Reseller Bandwidth', 'used' => 128849018880, 'limit' => null, 'is_bytes' => true, 'limit_read' => true],
                ['id' => 'reseller_accounts', 'label' => 'Reseller Accounts', 'used' => 24, 'limit' => 30, 'is_bytes' => false, 'limit_read' => true],
            ],
            'refreshed_at' => now(),
        ];
    }

    /**
     * Indicate that the last refresh failed; the values of the earlier refresh stay.
     */
    public function failed(RemoteServerErrorReason $reason = RemoteServerErrorReason::Timeout): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }
}
