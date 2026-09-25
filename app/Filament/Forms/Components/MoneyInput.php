<?php

namespace App\Filament\Forms\Components;

use App\Enums\Currency;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;

/**
 * Plain text input for an amount in Turkish format ("4.000,50"); stored as minor units (int).
 */
class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('money.amount'))
            ->placeholder('0,00')
            ->inputMode('decimal')
            ->formatStateUsing(fn (mixed $state): mixed => is_int($state) ? Money::formatAmount($state) : $state)
            ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) ? Money::parseAmount((string) $state) : null)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                try {
                    Money::parseAmount((string) $value);
                } catch (InvalidArgumentException) {
                    $fail(__('money.invalid_amount'));
                }
            });
    }

    /**
     * Show the currency label of the given sibling field (see CurrencySelect) after the amount.
     */
    public function currencyField(string $field = 'currency'): static
    {
        return $this->suffix(function (Get $get) use ($field): ?string {
            $currency = $get($field);

            $currency = $currency instanceof Currency ? $currency : Currency::tryFrom((string) $currency);

            return $currency?->getLabel();
        });
    }
}
