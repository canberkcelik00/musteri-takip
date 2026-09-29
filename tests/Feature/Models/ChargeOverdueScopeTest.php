<?php

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

it('matches the overdue scope to isOverdue, including a partially paid charge', function () {
    $overdue = Charge::factory()->create(['due_date' => '2026-06-14', 'amount' => 100000]);
    allocatePartial($overdue, 40000);

    $dueToday = Charge::factory()->create(['due_date' => '2026-06-15']);
    $future = Charge::factory()->create(['due_date' => '2026-06-16']);
    $paid = Charge::factory()->paid()->create(['due_date' => '2026-05-01']);
    $cancelled = Charge::factory()->cancelled()->create(['due_date' => '2026-05-01']);

    expect(Charge::query()->overdue()->pluck('id')->all())->toBe([$overdue->id])
        ->and($overdue->fresh()->isOverdue())->toBeTrue()
        ->and($dueToday->isOverdue())->toBeFalse()
        ->and($future->isOverdue())->toBeFalse()
        ->and($paid->isOverdue())->toBeFalse()
        ->and($cancelled->isOverdue())->toBeFalse();
});

it('matches dueThisMonth to open charges due from today through the end of the month, partially paid included', function () {
    $today = Charge::factory()->create(['due_date' => '2026-06-15']);
    $endOfMonth = Charge::factory()->create(['due_date' => '2026-06-30']);
    $partiallyPaid = Charge::factory()->create(['due_date' => '2026-06-20', 'amount' => 100000]);
    allocatePartial($partiallyPaid, 30000);

    $nextMonth = Charge::factory()->create(['due_date' => '2026-07-01']);
    $overdue = Charge::factory()->create(['due_date' => '2026-06-14']);
    $paidThisMonth = Charge::factory()->paid()->create(['due_date' => '2026-06-20']);

    expect(Charge::query()->dueThisMonth()->orderBy('due_date')->pluck('id')->all())
        ->toBe([$today->id, $partiallyPaid->id, $endOfMonth->id])
        ->and($nextMonth->fresh())->not->toBeNull()
        ->and(Charge::query()->dueThisMonth()->pluck('id')->all())
        ->not->toContain($nextMonth->id, $overdue->id, $paidThisMonth->id);
});

/**
 * Pay part of a charge so that it has an allocation, without going through RecordPayment
 * (models only, to keep this test focused on the scope).
 */
function allocatePartial(Charge $charge, int $amount): void
{
    $payment = Payment::factory()->for($charge->customer)->create(['currency' => $charge->currency, 'amount' => $amount]);
    PaymentAllocation::factory()->for($payment)->for($charge)->create(['amount' => $amount]);
}
