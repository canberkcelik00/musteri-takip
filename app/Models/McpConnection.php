<?php

namespace App\Models;

use App\Enums\McpAccess;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * What one Claude connection (an OAuth client the user approved) may do for that user (U-43). There is no row until
 * the user changes it, and no row means "read only"; the OAuth scope cannot tell the two apart, so this is it.
 *
 * @property int $user_id
 * @property string $oauth_client_id
 * @property McpAccess $access
 */
#[Fillable(['user_id', 'oauth_client_id', 'access'])]
class McpConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['access' => McpAccess::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Passport::clientModel(), 'oauth_client_id');
    }

    /**
     * The access the user gave that client; read only unless they said otherwise.
     */
    public static function accessFor(User $user, string $clientId): McpAccess
    {
        return static::query()->where('user_id', $user->id)->where('oauth_client_id', $clientId)->first()->access
            ?? McpAccess::ReadOnly;
    }
}
