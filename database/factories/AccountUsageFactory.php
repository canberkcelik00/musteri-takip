<?php

namespace Database\Factories;

use App\Enums\RemoteServerErrorReason;
use App\Models\AccountUsage;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUsage>
 */
class AccountUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'remote_username' => fake()->unique()->userName(),
            'resources' => [
                ['id' => 'disk_usage', 'label' => 'Disk Usage', 'used' => 1288490188, 'limit' => 5368709120, 'is_bytes' => true],
                ['id' => 'email_accounts', 'label' => 'Email Accounts', 'used' => 4, 'limit' => 10, 'is_bytes' => false],
            ],
            'databases' => [['name' => 'db_main', 'bytes' => 52428800]],
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
