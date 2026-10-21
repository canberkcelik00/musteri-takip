<?php

namespace App\Models;

use App\Enums\RemoteServerErrorReason;
use Database\Factories\AccountUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The last usage a server reported for one hosting account (a WHM account, or the cPanel user):
 * a cache of the server, not a history. `resources` is a list of
 * `{id, label, used, limit, is_bytes}` and `databases` a list of `{name, bytes}`; byte values
 * are bytes and a `limit` of null means unlimited. A failed refresh keeps the earlier values
 * and records `failed_at` and `failure_reason`. Percentages and "nearly full" are derived.
 *
 * @property list<array{id: string, label: string, used: int, limit: int|null, is_bytes: bool}>|null $resources
 * @property list<array{name: string, bytes: int}>|null $databases
 * @property Carbon|null $refreshed_at
 * @property Carbon|null $failed_at
 * @property RemoteServerErrorReason|null $failure_reason
 * @property Carbon|null $warning_sent_at
 */
#[Fillable(['server_id', 'remote_username', 'resources', 'databases', 'refreshed_at', 'failed_at', 'failure_reason', 'warning_sent_at'])]
class AccountUsage extends Model
{
    /** @use HasFactory<AccountUsageFactory> */
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
            'databases' => 'array',
            'refreshed_at' => 'datetime',
            'failed_at' => 'datetime',
            'failure_reason' => RemoteServerErrorReason::class,
            'warning_sent_at' => 'datetime',
        ];
    }
}
