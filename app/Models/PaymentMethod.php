<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A new method without a `sort_order` goes to the end of the list.
 */
#[Fillable(['name', 'sort_order'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use Archivable, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PaymentMethod $paymentMethod): void {
            if (! array_key_exists('sort_order', $paymentMethod->getAttributes())) {
                $paymentMethod->sort_order = (int) static::query()->max('sort_order') + 1;
            }
        });
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
