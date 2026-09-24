<?php

use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * An open 4.000 TL charge due on 01.03.2026 with the given amounts allocated from separate payments.
 *
 * @param  list<int>  $allocations
 */
function chargeWithAllocations(array $allocations = [], string $status = 'open'): Charge
{
    $charge = Charge::factory()->create([
        'amount' => 400000,
        'due_date' => '2026-03-01',
        'status' => $status,
    ]);

    foreach ($allocations as $amount) {
        Payment::factory()
            ->for($charge->customer)
            ->create(['amount' => $amount])
            ->allocations()
            ->create(['charge_id' => $charge->id, 'amount' => $amount]);
    }

    return $charge;
}

describe('remaining amount', function () {
    it('is the whole amount without allocations', function () {
        expect(chargeWithAllocations()->remainingAmount())->toBe(400000);
    });

    it('is reduced by a partial allocation', function () {
        expect(chargeWithAllocations([150000])->remainingAmount())->toBe(250000);
    });

    it('is zero when fully allocated', function () {
        expect(chargeWithAllocations([400000])->remainingAmount())->toBe(0);
    });

    it('adds up allocations from several payments', function () {
        expect(chargeWithAllocations([100000, 50050])->remainingAmount())->toBe(249950);
    });

    it('is the same when the allocation sum or the allocations are eager loaded', function () {
        $charge = chargeWithAllocations([100000, 50000]);

        $withSum = Charge::withSum('allocations', 'amount')->findOrFail($charge->id);
        $withAllocations = Charge::with('allocations')->findOrFail($charge->id);

        expect($withSum->remainingAmount())->toBe(250000)
            ->and($withAllocations->remainingAmount())->toBe(250000);
    });
});

describe('partially paid', function () {
    it('is determined from the allocations', function (array $allocations, string $status, bool $expected) {
        expect(chargeWithAllocations($allocations, $status)->isPartiallyPaid())->toBe($expected);
    })->with([
        'no allocation' => [[], 'open', false],
        'partial allocation' => [[150000], 'open', true],
        'full allocation' => [[400000], 'open', false],
        'cancelled with partial allocation' => [[150000], 'cancelled', false],
    ]);
});

describe('overdue', function () {
    it('is open, not overdue, during the whole due date', function (string $now) {
        $this->travelTo(Carbon::parse($now, 'Europe/Istanbul'));

        $charge = chargeWithAllocations();

        expect($charge->isOverdue())->toBeFalse()
            ->and($charge->daysOverdue())->toBe(0);
    })->with([
        'start of the due date' => '2026-03-01 00:00:00',
        'end of the due date' => '2026-03-01 23:59:59',
    ]);

    it('is overdue by one day from the start of the next day', function () {
        $this->travelTo(Carbon::parse('2026-03-02 00:00:00', 'Europe/Istanbul'));

        $charge = chargeWithAllocations();

        expect($charge->isOverdue())->toBeTrue()
            ->and($charge->daysOverdue())->toBe(1);
    });

    it('counts the days from the day after the due date', function () {
        $this->travelTo(Carbon::parse('2026-03-10 15:00:00', 'Europe/Istanbul'));

        expect(chargeWithAllocations()->daysOverdue())->toBe(9);
    });

    it('counts days at midnight in Istanbul, not UTC', function (string $utcNow, int $expectedDays) {
        $this->travelTo(Carbon::parse($utcNow, 'UTC'));

        expect(chargeWithAllocations()->daysOverdue())->toBe($expectedDays);
    })->with([
        '23:59:59 on the due date in Istanbul' => ['2026-03-01 20:59:59', 0],
        '00:00:00 on the next day in Istanbul' => ['2026-03-01 21:00:00', 1],
    ]);

    it('is overdue while an amount remains after a partial payment', function () {
        $this->travelTo(Carbon::parse('2026-03-05 12:00:00', 'Europe/Istanbul'));

        $charge = chargeWithAllocations([150000]);

        expect($charge->isOverdue())->toBeTrue()
            ->and($charge->daysOverdue())->toBe(4);
    });

    it('is not overdue when paid or cancelled', function (array $allocations, string $status) {
        $this->travelTo(Carbon::parse('2026-03-05 12:00:00', 'Europe/Istanbul'));

        $charge = chargeWithAllocations($allocations, $status);

        expect($charge->isOverdue())->toBeFalse()
            ->and($charge->daysOverdue())->toBe(0);
    })->with([
        'fully allocated' => [[400000], 'open'],
        'paid' => [[400000], 'paid'],
        'cancelled' => [[], 'cancelled'],
    ]);
});
