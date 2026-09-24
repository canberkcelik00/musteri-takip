<?php

namespace App\Support;

use App\Enums\Currency;
use InvalidArgumentException;

/**
 * An amount of money in minor units (kuruş/cent) and its currency.
 *
 * Arithmetic uses integers and string operations only; floats are never involved.
 */
final readonly class Money
{
    private const string AMOUNT_PATTERN = '/^(?<whole>\d{1,3}(?:\.\d{3})+|\d+)(?:,(?<fraction>\d{1,2}))?$/';

    public function __construct(public int $amount, public Currency $currency)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    /**
     * Create money from Turkish formatted input such as "4.000,50".
     */
    public static function parse(string $input, Currency $currency): self
    {
        return new self(self::parseAmount($input), $currency);
    }

    /**
     * Convert Turkish formatted input to minor units: "4.000,50" becomes 400050.
     *
     * Dots are only accepted as thousands separators in groups of three and the
     * comma separates at most two decimal digits.
     */
    public static function parseAmount(string $input): int
    {
        if (preg_match(self::AMOUNT_PATTERN, trim($input), $matches) !== 1) {
            throw new InvalidArgumentException("Invalid money amount [{$input}].");
        }

        $wholeDigits = ltrim(str_replace('.', '', $matches['whole']), '0');
        $cents = (int) str_pad($matches['fraction'] ?? '', 2, '0');

        $maxWholeDigits = (string) intdiv(PHP_INT_MAX, 100);

        $exceedsMax = strlen($wholeDigits) > strlen($maxWholeDigits)
            || (strlen($wholeDigits) === strlen($maxWholeDigits) && strcmp($wholeDigits, $maxWholeDigits) > 0)
            || ($wholeDigits === $maxWholeDigits && $cents > PHP_INT_MAX % 100);

        if ($exceedsMax) {
            throw new InvalidArgumentException("Money amount [{$input}] is too large.");
        }

        return (int) $wholeDigits * 100 + $cents;
    }

    /**
     * Format as Turkish text such as "4.000,50 TL"; cents are omitted when zero ("4.000 TL").
     */
    public function format(): string
    {
        $wholeDigits = (string) intdiv($this->amount, 100);
        $cents = $this->amount % 100;

        $formatted = strrev(implode('.', str_split(strrev($wholeDigits), 3)));

        if ($cents !== 0) {
            $formatted .= ','.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
        }

        return $formatted.' '.$this->currency->getLabel();
    }

    /**
     * Add money of the same currency; different currencies are never mixed.
     */
    public function add(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException(
                "Cannot add {$other->currency->value} to {$this->currency->value}.",
            );
        }

        if ($other->amount > PHP_INT_MAX - $this->amount) {
            throw new InvalidArgumentException('Money total is too large.');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Total the given money per currency, keyed by currency value in `Currency::cases()` order.
     *
     * @param  iterable<self>  $items
     * @return array<string, self>
     */
    public static function sumByCurrency(iterable $items): array
    {
        $totals = [];

        foreach ($items as $money) {
            $key = $money->currency->value;
            $totals[$key] = isset($totals[$key]) ? $totals[$key]->add($money) : $money;
        }

        $ordered = [];

        foreach (Currency::cases() as $currency) {
            if (isset($totals[$currency->value])) {
                $ordered[$currency->value] = $totals[$currency->value];
            }
        }

        return $ordered;
    }
}
