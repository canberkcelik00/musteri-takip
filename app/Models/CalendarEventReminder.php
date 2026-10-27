<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reminder already sent for one occurrence (`occurs_on`) of an event. No row means it was not sent yet; the
 * unique (event, day) pair is what keeps a second run from sending it twice.
 *
 * @property Carbon $occurs_on
 * @property Carbon $sent_at
 */
#[Fillable(['calendar_event_id', 'occurs_on', 'sent_at'])]
class CalendarEventReminder extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurs_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
