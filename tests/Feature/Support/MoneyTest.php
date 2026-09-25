<?php

use App\Enums\Currency;
use App\Support\Money;

it('parses Turkish formatted amounts to minor units', function (string $input, int $expected) {
    expect(Money::parseAmount($input))->toBeInt()->toBe($expected);
})->with([
    'plain' => ['4000', 400000],
    'thousands separator' => ['4.000', 400000],
    'one decimal digit' => ['4.000,5', 400050],
    'two decimal digits' => ['4.000,50', 400050],
    'decimals without separator' => ['4000,50', 400050],
    'millions' => ['1.234.567,89', 123456789],
    'surrounding whitespace' => ['  1.900 ', 190000],
    'zero' => ['0', 0],
    'zero with decimals' => ['0,00', 0],
    'only cents' => ['0,05', 5],
]);

it('rejects input that is not a Turkish formatted amount', function (string $input) {
    Money::parseAmount($input);
})->throws(InvalidArgumentException::class)->with([
    'empty' => [''],
    'blank' => ['   '],
    'negative' => ['-100'],
    'English format' => ['4,000.50'],
    'dot as decimal' => ['4.5'],
    'three decimal digits' => ['4,505'],
    'broken thousands group' => ['4.00,50'],
    'letters' => ['abc'],
    'currency suffix' => ['100 TL'],
    'missing whole part' => [',50'],
]);

it('handles large amounts up to the integer limit without floats', function () {
    $input = '92.233.720.368.547.758,07';

    expect(Money::parseAmount($input))->toBe(PHP_INT_MAX)
        ->and((new Money(PHP_INT_MAX, Currency::Try))->format())->toBe('92.233.720.368.547.758,07 TL')
        ->and(Money::parse($input, Currency::Try)->format())->toBe($input.' TL');
});

it('rejects amounts beyond the integer limit', function (string $input) {
    Money::parseAmount($input);
})->throws(InvalidArgumentException::class)->with([
    'cents over the limit' => ['92.233.720.368.547.758,08'],
    'whole over the limit' => ['92.233.720.368.547.759'],
    'more digits' => ['1.000.000.000.000.000.000'],
]);

it('formats minor units as Turkish text', function (int $amount, Currency $currency, string $expected) {
    expect((new Money($amount, $currency))->format())->toBe($expected);
})->with([
    'with cents' => [400050, Currency::Try, '4.000,50 TL'],
    'whole amount hides cents' => [400000, Currency::Try, '4.000 TL'],
    'single cent digit is padded' => [400005, Currency::Try, '4.000,05 TL'],
    'below one' => [999, Currency::Try, '9,99 TL'],
    'zero' => [0, Currency::Try, '0 TL'],
    'only cents' => [5, Currency::Try, '0,05 TL'],
    'no separator under a thousand' => [35000, Currency::Usd, '350 USD'],
    'millions' => [100000000, Currency::Eur, '1.000.000 EUR'],
]);

it('rejects negative amounts', function () {
    new Money(-1, Currency::Try);
})->throws(InvalidArgumentException::class);

it('adds money of the same currency', function () {
    $total = (new Money(150000, Currency::Try))->add(new Money(40000, Currency::Try));

    expect($total->amount)->toBe(190000)
        ->and($total->currency)->toBe(Currency::Try);
});

it('never adds different currencies', function () {
    (new Money(150000, Currency::Try))->add(new Money(40000, Currency::Usd));
})->throws(InvalidArgumentException::class);

it('rejects a total beyond the integer limit', function () {
    (new Money(PHP_INT_MAX, Currency::Try))->add(new Money(1, Currency::Try));
})->throws(InvalidArgumentException::class);

it('sums money per currency without mixing them', function () {
    $totals = Money::sumByCurrency([
        new Money(35000, Currency::Usd),
        new Money(1000000, Currency::Try),
        new Money(200000, Currency::Try),
    ]);

    expect(array_keys($totals))->toBe(['TRY', 'USD'])
        ->and($totals['TRY']->amount)->toBe(1200000)
        ->and($totals['TRY']->currency)->toBe(Currency::Try)
        ->and($totals['USD']->amount)->toBe(35000)
        ->and($totals['USD']->currency)->toBe(Currency::Usd);
});

it('returns no totals for no money', function () {
    expect(Money::sumByCurrency([]))->toBe([]);
});

it('formats minor units without a currency', function (int $amount, string $expected) {
    expect(Money::formatAmount($amount))->toBe($expected);
})->with([
    'with cents' => [400050, '4.000,50'],
    'whole amount hides cents' => [400000, '4.000'],
    'single cent digit is padded' => [5, '0,05'],
    'zero' => [0, '0'],
]);

it('formats per-currency totals on one line', function () {
    $totals = Money::sumByCurrency([
        new Money(35000, Currency::Usd),
        new Money(1000000, Currency::Try),
        new Money(200000, Currency::Try),
    ]);

    expect(Money::formatTotals($totals))->toBe('12.000 TL + 350 USD')
        ->and(Money::formatTotals(['TRY' => new Money(400050, Currency::Try)]))->toBe('4.000,50 TL')
        ->and(Money::formatTotals([]))->toBe('—');
});

arch('money never uses float functions')
    ->expect(Money::class)
    ->not->toUse(['floatval', 'round', 'floor', 'ceil', 'number_format', 'fdiv']);
