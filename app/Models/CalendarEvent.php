<?php

namespace App\Models;

use App\Enums\EventRepeat;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One row is the whole series: the repeats of a weekly or monthly event are expanded when the calendar is read, never
 * stored. `start_time` and `end_time` are `H:i:s` strings and stay empty on an all-day event. `created_by` is not
 * fillable; only the actions in App\Actions\Calendar set it. The model is not called `Event`, which would be mistaken
 * for Laravel's event system.
 *
 * @property Carbon $starts_on
 * @property Carbon|null $repeat_until
 * @property EventRepeat $repeat
 * @property string|null $start_time
 * @property string|null $end_time
 */
#[Fillable([
    'title', 'starts_on', 'start_time', 'end_time', 'all_day', 'customer_id', 'location', 'notes',
    'remind_minutes', 'repeat', 'repeat_until',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_event_user');
    }

    /**
     * @return HasMany<CalendarEventReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarEventReminder::class);
    }

    public function repeats(): bool
    {
        return $this->repeat !== EventRepeat::None;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'all_day' => 'boolean',
            'remind_minutes' => 'integer',
            'repeat' => EventRepeat::class,
            'repeat_until' => 'date',
        ];
    }
}
