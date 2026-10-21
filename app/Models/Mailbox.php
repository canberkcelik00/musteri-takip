<?php

namespace App\Models;

use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One e-mail address of a hosting account as the server last reported it (a cache, not a
 * history). A `quota_bytes` of null means no quota. Percentages are derived, not stored.
 *
 * @property int $used_bytes
 * @property int|null $quota_bytes
 * @property bool $login_suspended
 * @property bool $incoming_suspended
 * @property Carbon|null $warning_sent_at
 */
#[Fillable(['server_id', 'remote_username', 'address', 'domain', 'used_bytes', 'quota_bytes', 'login_suspended', 'incoming_suspended', 'warning_sent_at'])]
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_bytes' => 'integer',
            'quota_bytes' => 'integer',
            'login_suspended' => 'boolean',
            'incoming_suspended' => 'boolean',
            'warning_sent_at' => 'datetime',
        ];
    }
}
