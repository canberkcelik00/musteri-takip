<?php

namespace App\Models;

use Database\Factories\SiteIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time a site stopped answering (G-16.6). It opens when the probe fails twice in a row and closes on the first
 * answer; `ended_at` empty means the site is still down. An incident is history and is never deleted. `domain` is the
 * domain at the time, so a later change of the item's domain does not rewrite it.
 *
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['customer_service_item_id', 'domain', 'started_at', 'ended_at', 'reason'])]
class SiteIncident extends Model
{
    /** @use HasFactory<SiteIncidentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CustomerServiceItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CustomerServiceItem::class, 'customer_service_item_id');
    }

    /**
     * Incidents of sites that are still down.
     *
     * @param  Builder<SiteIncident>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
