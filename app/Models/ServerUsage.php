<?php

namespace App\Models;

use App\Enums\RemoteServerErrorReason;
use App\Enums\UsageLevel;
use Database\Factories\ServerUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The last totals a WHM server reported for the whole reseller (docs/DECISIONS.md T-118): a cache
 * of the server, not a history. `resources` is a list of `{id, label, used, limit, is_bytes,
 * limit_read}`; byte values are bytes, a `limit` of null means unlimited, and `limit_read` false
 * means the limit could not be read (unknown, so never a warning). A failed refresh keeps the
 * earlier values and records `failed_at` and `failure_reason`. `warned_levels` maps a resource id
 * to the {@see UsageLevel} value already e-mailed, `warning_sent_at` is the last e-mail.
 *
 * @property list<array{id: string, label: string, used: int, limit: int|null, is_bytes: bool, limit_read: bool}>|null $resources
 * @property list<array{username: string, domain: string, plan: string|null, suspended: bool, suspend_reason: string|null, disk: int, disk_limit: int|null, bw: int|null, bw_limit: int|null}>|null $accounts one row per account of the reseller; a null limit is unlimited, a null `bw` was not readable
 * @property Carbon|null $refreshed_at
 * @property Carbon|null $failed_at
 * @property RemoteServerErrorReason|null $failure_reason
 * @property array<string, string>|null $warned_levels
 * @property Carbon|null $warning_sent_at
 */
#[Fillable(['server_id', 'resources', 'accounts', 'refreshed_at', 'failed_at', 'failure_reason', 'warned_levels', 'warning_sent_at'])]
class ServerUsage extends Model
{
    /** @use HasFactory<ServerUsageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * When the server was last asked, whether or not it answered.
     */
    public function lastAttemptAt(): ?Carbon
    {
        return collect([$this->refreshed_at, $this->failed_at])->filter()->max();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resources' => 'array',
            'accounts' => 'array',
            'refreshed_at' => 'datetime',
            'failed_at' => 'datetime',
            'failure_reason' => RemoteServerErrorReason::class,
            'warned_levels' => 'array',
            'warning_sent_at' => 'datetime',
        ];
    }
}
