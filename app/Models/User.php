<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $name
 * @property string $email
 * @property Carbon|null $invited_at
 * @property Carbon|null $deactivated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Roles and permissions live in the `web` guard. The Claude endpoint authenticates with the `api` guard (U-43),
     * and without this the permission checks would look in `api` there and find nothing.
     */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'invited_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * The id of the OAuth client (the Claude app) behind the access token of this request; null outside the Claude
     * endpoint.
     */
    public function currentMcpClientId(): ?string
    {
        $token = $this->currentAccessToken();

        return $token instanceof AccessToken ? $token->oauth_client_id : null;
    }

    /**
     * Whether the user may sign in: not deactivated.
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Whether the user was invited, did not choose a password yet and is not deactivated: the invitation link works.
     */
    public function isInvitationPending(): bool
    {
        return $this->isActive() && ! $this->hasPassword() && $this->invited_at !== null;
    }

    /**
     * How many active users hold the administrator role.
     */
    public static function activeAdministratorCount(): int
    {
        return static::query()->role(Role::Admin->value)->whereNull('deactivated_at')->count();
    }

    /**
     * Whether the user chose a password yet; an invited user has not.
     */
    public function hasPassword(): bool
    {
        return filled($this->getAuthPassword());
    }
}
