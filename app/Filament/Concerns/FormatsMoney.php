<?php

namespace App\Filament\Concerns;

use App\Enums\Currency;
use App\Support\Money;
use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Shared display logic of MoneyColumn and MoneyEntry.
 *
 * The state may be an int (minor units, currency taken from the record), a Money, or per-currency
 * Money totals (as returned by Money::sumByCurrency() or Customer::creditBalances()), which are
 * listed one per line, or on one line as "12.000 TL + 350 USD" with singleLine(). No state shows "—".
 */
trait FormatsMoney
{
    protected string|Closure $currencyAttribute = 'currency';

    protected bool|Closure $isSingleLine = false;

    protected function setUpMoneyFormatting(): void
    {
        $this
            ->placeholder(__('money.empty'))
            ->listWithLineBreaks()
            ->formatStateUsing(fn (mixed $state): string => $this->formatMoneyState($state));
    }

    /**
     * Name of the record attribute that holds the currency of an int state.
     */
    public function currencyAttribute(string|Closure $attribute): static
    {
        $this->currencyAttribute = $attribute;

        return $this;
    }

    /**
     * Show per-currency totals on one line instead of one per line.
     */
    public function singleLine(bool|Closure $condition = true): static
    {
        $this->isSingleLine = $condition;

        return $this;
    }

    public function isSingleLine(): bool
    {
        return (bool) $this->evaluate($this->isSingleLine);
    }

    public function getState(): mixed
    {
        $state = parent::getState();

        if (! $this->isSingleLine() || ! is_array($state) || $state === []) {
            return $state;
        }

        $isMoneyList = collect($state)->every(fn (mixed $item): bool => $item instanceof Money);

        return $isMoneyList ? Money::formatTotals($state) : $state;
    }

    private function formatMoneyState(mixed $state): string
    {
        if ($state instanceof Money) {
            return $state->format();
        }

        if (is_int($state)) {
            return $this->moneyFromAmount($state)->format();
        }

        return (string) $state;
    }

    private function moneyFromAmount(int $amount): Money
    {
        $record = $this->getRecord();
        $currency = $record instanceof Model ? $record->getAttribute($this->evaluate($this->currencyAttribute)) : null;

        $currency = $currency instanceof Currency ? $currency : Currency::tryFrom((string) $currency);

        if ($currency === null) {
            throw new LogicException('An int amount needs a record with a currency attribute.');
        }

        return new Money($amount, $currency);
    }
}
