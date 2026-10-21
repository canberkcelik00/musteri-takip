<?php

namespace Database\Factories;

use App\Models\Mailbox;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'server_id' => Server::factory(),
            'remote_username' => fake()->userName(),
            'address' => fake()->userName().'@'.$domain,
            'domain' => $domain,
            'used_bytes' => 100 * 1024 * 1024,
            'quota_bytes' => 1024 * 1024 * 1024,
            'login_suspended' => false,
            'incoming_suspended' => false,
        ];
    }

    /**
     * Indicate that the mailbox has no quota.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => ['quota_bytes' => null]);
    }
}
