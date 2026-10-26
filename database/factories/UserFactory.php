<?php

namespace Database\Factories;

use App\Actions\Users\SyncRolesAndPermissions;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * An administrator: holds every permission.
     */
    public function admin(): static
    {
        return $this->withRole(Role::Admin);
    }

    /**
     * A developer: works with customers, sees no money.
     */
    public function developer(): static
    {
        return $this->withRole(Role::Developer);
    }

    /**
     * A user who was deactivated and cannot sign in.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
        ]);
    }

    /**
     * A user who was invited and has not chosen a password yet.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'email_verified_at' => null,
            'invited_at' => now(),
        ]);
    }

    private function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            app(SyncRolesAndPermissions::class)->handle();

            $user->assignRole($role->value);
        });
    }
}
