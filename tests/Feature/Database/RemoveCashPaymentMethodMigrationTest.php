<?php

use App\Models\Payment;
use App\Models\PaymentMethod;

/**
 * The migration has already run when the test database is built, so each test adds a "Nakit"
 * row back and runs the migration's up() again; the rows are rolled back with the test.
 */
function removeCashMigration(): object
{
    return require database_path('migrations/2026_10_19_100000_remove_cash_payment_method.php');
}

it('has no cash method in a fresh database', function () {
    expect(PaymentMethod::query()->where('name', 'Nakit')->exists())->toBeFalse()
        ->and(PaymentMethod::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Havale/EFT', 'Kredi Kartı']);
});

it('deletes a cash method no payment uses', function () {
    PaymentMethod::query()->create(['name' => 'Nakit']);

    removeCashMigration()->up();

    expect(PaymentMethod::query()->where('name', 'Nakit')->exists())->toBeFalse()
        ->and(PaymentMethod::query()->count())->toBe(2);
});

it('archives a cash method that payments use and keeps those payments', function () {
    $cash = PaymentMethod::query()->create(['name' => 'Nakit']);
    $payment = Payment::factory()->create(['payment_method_id' => $cash->id]);

    removeCashMigration()->up();

    expect($cash->refresh()->isArchived())->toBeTrue()
        ->and($payment->refresh()->payment_method_id)->toBe($cash->id);
});

it('leaves the cash method alone when it is the only active one', function () {
    PaymentMethod::query()->get()->each->archive();
    $cash = PaymentMethod::query()->create(['name' => 'Nakit']);

    removeCashMigration()->up();

    expect($cash->refresh()->isArchived())->toBeFalse();
});

it('does nothing when there is no cash method, and again on a second run', function () {
    removeCashMigration()->up();
    removeCashMigration()->up();

    expect(PaymentMethod::query()->count())->toBe(2);
});

it('keeps the first archive time when run again', function () {
    $cash = PaymentMethod::query()->create(['name' => 'Nakit']);
    Payment::factory()->create(['payment_method_id' => $cash->id]);
    removeCashMigration()->up();
    $archivedAt = $cash->refresh()->archived_at;

    $this->travelTo(now()->addDay());
    removeCashMigration()->up();

    expect($cash->refresh()->archived_at->equalTo($archivedAt))->toBeTrue();
});
