<?php

use App\Models\Charge;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

it('treats an open charge due today or within 7 days as due soon', function () {
    $today = Charge::factory()->create(['due_date' => '2026-06-15']);
    $inSevenDays = Charge::factory()->create(['due_date' => '2026-06-22']);

    expect($today->isDueSoon())->toBeTrue()
        ->and($inSevenDays->isDueSoon())->toBeTrue();
});

it('does not treat a charge due in more than 7 days as due soon', function () {
    $charge = Charge::factory()->create(['due_date' => '2026-06-23']);

    expect($charge->isDueSoon())->toBeFalse();
});

it('does not treat an overdue charge as due soon', function () {
    $charge = Charge::factory()->create(['due_date' => '2026-06-14']);

    expect($charge->isDueSoon())->toBeFalse();
});

it('does not treat a paid or cancelled charge as due soon', function () {
    $paid = Charge::factory()->paid()->create(['due_date' => '2026-06-16']);
    $cancelled = Charge::factory()->cancelled()->create(['due_date' => '2026-06-16']);

    expect($paid->isDueSoon())->toBeFalse()
        ->and($cancelled->isDueSoon())->toBeFalse();
});

it('matches the dueSoon scope to the isDueSoon method', function () {
    $dueSoon = Charge::factory()->create(['due_date' => '2026-06-18']);
    $tooFar = Charge::factory()->create(['due_date' => '2026-06-25']);
    $overdue = Charge::factory()->create(['due_date' => '2026-06-01']);

    expect(Charge::query()->dueSoon()->pluck('id')->all())->toBe([$dueSoon->id])
        ->and($tooFar->isDueSoon())->toBeFalse()
        ->and($overdue->isDueSoon())->toBeFalse();
});
