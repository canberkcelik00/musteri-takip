<?php

namespace App\Support\Ui;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * An amount typed in Turkish format ("4.000,50"); empty is left to `required`/`nullable`.
 */
final class MoneyAmountRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        try {
            Money::parseAmount((string) $value);
        } catch (InvalidArgumentException) {
            $fail(__('money.invalid_amount'));
        }
    }

    /**
     * The typed amount in minor units, or null when it is empty or not an amount.
     */
    public static function minorUnits(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Money::parseAmount((string) $value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
