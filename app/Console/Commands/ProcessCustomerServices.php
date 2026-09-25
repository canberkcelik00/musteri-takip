<?php

namespace App\Console\Commands;

use App\Actions\CustomerServices\AdvanceDueCustomerServicePeriods;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-customer-services')]
#[Description('Advance ended customer service periods and create due renewal charges')]
class ProcessCustomerServices extends Command
{
    /**
     * Execute the console command. Periods are advanced first (creating any missed
     * renewal charge on the way), then the renewal charges whose window has started
     * are created, so one run catches up after missed runs.
     */
    public function handle(
        AdvanceDueCustomerServicePeriods $advanceDueCustomerServicePeriods,
        CreateDueRenewalCharges $createDueRenewalCharges,
    ): int {
        $failures = 0;
        $countFailure = function () use (&$failures): void {
            $failures++;
        };

        $advanced = $advanceDueCustomerServicePeriods->handle($countFailure);
        $charges = $createDueRenewalCharges->handle($countFailure);

        $this->components->info(__('customer_services.scheduler_advanced', ['count' => $advanced->count()]));
        $this->components->info(__('customer_services.scheduler_charges_created', ['count' => $charges->count()]));

        if ($failures > 0) {
            $this->components->error(__('customer_services.scheduler_failures', ['count' => $failures]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
