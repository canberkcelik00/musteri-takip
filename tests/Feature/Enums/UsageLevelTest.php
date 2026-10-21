<?php

use App\Enums\UsageLevel;

it('has no level below the threshold', function (int $used, int $limit) {
    expect(UsageLevel::of($used, $limit, 90))->toBeNull();
})->with([
    'nothing used' => [0, 100],
    'one under' => [89, 100],
    'whole percent under, fraction would round up' => [899, 1000],
]);

it('is nearly full from the threshold up to the limit', function (int $used, int $limit) {
    expect(UsageLevel::of($used, $limit, 90))->toBe(UsageLevel::NearlyFull);
})->with([
    'at the threshold' => [90, 100],
    'just over' => [900, 1000],
    'one under the limit' => [99, 100],
]);

it('is full at and over the limit', function (int $used) {
    expect(UsageLevel::of($used, 100, 90))->toBe(UsageLevel::Full);
})->with([100, 101, 5000]);

it('has no level without a limit', function (?int $limit) {
    expect(UsageLevel::of(1000, $limit, 90))->toBeNull();
})->with([
    'unlimited' => [null],
    'zero is read as unlimited' => [0],
]);

it('follows the threshold it is given', function () {
    expect(UsageLevel::of(60, 100, 50))->toBe(UsageLevel::NearlyFull)
        ->and(UsageLevel::of(60, 100, 70))->toBeNull()
        ->and(UsageLevel::of(99, 100, 100))->toBeNull()
        ->and(UsageLevel::of(100, 100, 100))->toBe(UsageLevel::Full);
});

it('rounds the percent down and has none without a limit', function () {
    expect(UsageLevel::percent(999, 1000))->toBe(99)
        ->and(UsageLevel::percent(1, 3))->toBe(33)
        ->and(UsageLevel::percent(150, 100))->toBe(150)
        ->and(UsageLevel::percent(5, null))->toBeNull()
        ->and(UsageLevel::percent(5, 0))->toBeNull();
});

it('ranks full above nearly full and names them in Turkish', function () {
    expect(UsageLevel::Full->severity())->toBeGreaterThan(UsageLevel::NearlyFull->severity())
        ->and(UsageLevel::NearlyFull->getLabel())->toBe('Dolmak üzere')
        ->and(UsageLevel::Full->getLabel())->toBe('Dolu')
        ->and(UsageLevel::NearlyFull->getColor())->toBe('warning')
        ->and(UsageLevel::Full->getColor())->toBe('danger');
});
