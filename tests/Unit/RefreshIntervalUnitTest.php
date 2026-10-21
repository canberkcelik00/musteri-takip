<?php

use App\Enums\RefreshIntervalUnit;

it('splits minutes into the largest unit that divides them evenly', function (int $minutes, int $value, RefreshIntervalUnit $unit) {
    expect(RefreshIntervalUnit::split($minutes))->toBe([$value, $unit]);
})->with([
    [60, 1, RefreshIntervalUnit::Hour],
    [90, 90, RefreshIntervalUnit::Minute],
    [120, 2, RefreshIntervalUnit::Hour],
    [1440, 1, RefreshIntervalUnit::Day],
    [2880, 2, RefreshIntervalUnit::Day],
    [15, 15, RefreshIntervalUnit::Minute],
]);

it('turns a number and a unit into minutes', function () {
    expect(RefreshIntervalUnit::Minute->minutes(30))->toBe(30)
        ->and(RefreshIntervalUnit::Hour->minutes(4))->toBe(240)
        ->and(RefreshIntervalUnit::Day->minutes(2))->toBe(2880);
});
