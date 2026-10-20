<?php

use App\Support\Servers\AnniversaryStart;
use Carbon\CarbonImmutable;

it('returns the latest anniversary that is not after today', function (string $date, string $today, string $expected) {
    $start = AnniversaryStart::covering(CarbonImmutable::parse($date), CarbonImmutable::parse($today));

    expect($start->toDateString())->toBe($expected);
})->with([
    'earlier this year' => ['2024-03-15', '2026-10-20', '2026-03-15'],
    'later this year' => ['2024-12-01', '2026-10-20', '2025-12-01'],
    'today is the anniversary' => ['2024-10-20', '2026-10-20', '2026-10-20'],
    'day before the anniversary' => ['2024-10-21', '2026-10-20', '2025-10-21'],
    'opened this year' => ['2026-01-05', '2026-10-20', '2026-01-05'],
    'opened today' => ['2026-10-20', '2026-10-20', '2026-10-20'],
    'in the future' => ['2027-01-01', '2026-10-20', '2027-01-01'],
    '29 February in a non-leap year' => ['2024-02-29', '2026-10-20', '2026-02-28'],
    '29 February before the anniversary' => ['2024-02-29', '2026-02-27', '2025-02-28'],
    '29 February in a leap year' => ['2023-02-28', '2028-03-01', '2028-02-28'],
    '29 February kept in a leap year' => ['2020-02-29', '2028-03-01', '2028-02-29'],
]);

it('always leaves a period that has not ended yet', function () {
    $today = CarbonImmutable::parse('2026-10-20');

    foreach (['2019-02-28', '2020-02-29', '2024-10-20', '2025-10-21', '2022-01-31'] as $date) {
        $start = AnniversaryStart::covering(CarbonImmutable::parse($date), $today);

        expect($start->lessThanOrEqualTo($today))->toBeTrue()
            ->and($start->addMonthsNoOverflow(12)->greaterThan($today))->toBeTrue();
    }
});
