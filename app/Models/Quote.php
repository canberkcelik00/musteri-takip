<?php

namespace App\Models;

use App\Enums\QuoteDisplayStatus;
use App\Enums\QuoteStatus;
use App\Support\Money;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Either `customer_id` or the manually written recipient (name and e-mail) is filled; a CHECK
 * constraint enforces it. "Expired" is never stored: a sent quote is valid through the whole
 * `valid_until` day and expires the day after (docs/DATA_MODEL.md).
 *
 * @property QuoteStatus $status
 * @property Carbon $valid_until
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 */
#[Fillable([
    'number',
    'customer_id',
    'recipient_name',
    'recipient_company',
    'recipient_phone',
    'recipient_email',
    'status',
    'valid_until',
    'sent_at',
    'sent_to',
    'accepted_at',
    'rejected_at',
    'accepted_by',
    'notes',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who accepted the quote; none for a quote accepted before Faz 12.
     *
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * A sent quote past the end of its `valid_until` day. Days are counted in the application
     * time zone (Europe/Istanbul), not the database's.
     */
    public function isExpired(): bool
    {
        if ($this->status !== QuoteStatus::Sent) {
            return false;
        }

        $validUntil = Carbon::parse($this->valid_until->toDateString(), config('app.timezone'));

        return $validUntil->lt(Carbon::today(config('app.timezone')));
    }

    /**
     * The stored status, or "expired" for a sent quote whose validity has ended.
     */
    public function displayStatus(): QuoteDisplayStatus
    {
        if ($this->isExpired()) {
            return QuoteDisplayStatus::Expired;
        }

        return QuoteDisplayStatus::from($this->status->value);
    }

    /**
     * Total of the paid items per currency, keyed by currency value; free items add nothing and
     * different currencies are never added together.
     *
     * @return array<string, Money>
     */
    public function totals(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return Money::sumByCurrency(
            $items->reject(fn (QuoteItem $item): bool => $item->is_free)
                ->map(fn (QuoteItem $item): Money => new Money($item->price, $item->currency))
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
