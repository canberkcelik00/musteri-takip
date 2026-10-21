<?php

namespace App\Models;

use App\Enums\UsageLevel;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One e-mail address of a hosting account as the server last reported it (a cache, not a
 * history). A `quota_bytes` of null means no quota. Percentages are derived, not stored; only
 * what was already warned about is: `warned_level` is the {@see UsageLevel} the owner was
 * e-mailed for, `warning_sent_at` the last e-mail.
 *
 * @property int $used_bytes
 * @property int|null $quota_bytes
 * @property bool $login_suspended
 * @property bool $incoming_suspended
 * @property UsageLevel|null $warned_level
 * @property Carbon|null $warning_sent_at
 */
#[Fillable(['server_id', 'remote_username', 'address', 'domain', 'used_bytes', 'quota_bytes', 'login_suspended', 'incoming_suspended', 'warned_level', 'warning_sent_at'])]
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
            'warned_level' => UsageLevel::class,
            'warning_sent_at' => 'datetime',
        ];
    }
}
