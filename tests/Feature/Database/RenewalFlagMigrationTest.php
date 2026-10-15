<?php

use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The migration fills `charges.is_renewal` for the charges that exist when it runs: a period
 * charge is a renewal when it starts after the service's start date, or when another charge of
 * the service ends where it starts. A charge that looks like a first period (it starts on the
 * service's start date and has no earlier period charge) stays unmarked, which only means no
 * reminder is sent for it: the safe side of an ambiguous case.
 *
 * Running the migration again needs the columns gone, and MySQL commits DDL implicitly, so the
 * rows created here are not rolled back by RefreshDatabase and are deleted by hand.
 */
it('marks existing renewal charges and leaves first period and manual charges alone', function () {
    $migration = require database_path('migrations/2026_10_15_100000_add_renewal_reminder_columns_to_charges_table.php');
    $customerIds = [];

    try {
        $migration->down();

        $firstPeriod = CustomerService::factory()->create(['start_date' => '2026-03-01', 'end_date' => '2027-03-01']);
        $startedEarlier = CustomerService::factory()->create(['start_date' => '2026-03-01', 'end_date' => '2027-03-01']);
        $ambiguous = CustomerService::factory()->create(['start_date' => '2027-03-01', 'end_date' => '2028-03-01']);
        $withPrevious = CustomerService::factory()->create(['start_date' => '2027-03-01', 'end_date' => '2028-03-01']);
        $customerIds = [$firstPeriod->customer_id, $startedEarlier->customer_id, $ambiguous->customer_id, $withPrevious->customer_id];

        $insert = fn (CustomerService $service, string $start, string $end) => Charge::factory()
            ->forCustomerService($service)
            ->create(['period_start' => $start, 'period_end' => $end, 'due_date' => $start])
            ->id;

        $firstPeriodId = $insert($firstPeriod, '2026-03-01', '2027-03-01');
        $startedEarlierId = $insert($startedEarlier, '2027-03-01', '2028-03-01');
        $ambiguousId = $insert($ambiguous, '2027-03-01', '2028-03-01');
        $previousId = $insert($withPrevious, '2026-03-01', '2027-03-01');
        $renewalOfPreviousId = $insert($withPrevious, '2027-03-01', '2028-03-01');
        $manualId = Charge::factory()->create(['customer_id' => $firstPeriod->customer_id])->id;

        $migration->up();

        $marked = Charge::query()->where('is_renewal', true)->orderBy('id')->pluck('id')->all();

        expect($marked)->toBe([$startedEarlierId, $renewalOfPreviousId])
            ->and($marked)->not->toContain($firstPeriodId, $ambiguousId, $previousId, $manualId)
            ->and(Charge::query()->whereNotNull('reminder_sent_at')->exists())->toBeFalse();
    } finally {
        if (! Schema::hasColumn('charges', 'is_renewal')) {
            $migration->up();
        }

        DB::table('charges')->delete();
        DB::table('customer_service_items')->delete();
        DB::table('customer_services')->delete();
        DB::table('contacts')->delete();
        DB::table('catalog_services')->delete();
        Customer::query()->whereKey(array_filter($customerIds))->delete();
    }
});
