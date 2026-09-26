<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\TracksUsage;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A new method without a `sort_order` goes to the end of the list.
 */
#[Fillable(['name', 'sort_order'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use Archivable, HasFactory, TracksUsage;

    protected static function booted(): void
    {
        static::creating(function (PaymentMethod $paymentMethod): void {
            if (! array_key_exists('sort_order', $paymentMethod->getAttributes())) {
                $paymentMethod->sort_order = (int) static::query()->max('sort_order') + 1;
            }
        });
    }

    /**
     * A method used by a payment is archived, not deleted.
     *
     * @return list<string>
     */
    protected function usageRelations(): array
    {
        return ['payments'];
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
