<?php

namespace App\Actions\CustomerServices;

use App\Actions\Charges\CreateCharge;
use App\Enums\CustomerServiceStatus;
use App\Exceptions\InvalidCustomerService;
use App\Models\Charge;
use App\Models\CustomerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReactivateCustomerService
{
    public function __construct(
        private CreateCharge $createCharge,
        private CreateRenewalCharge $createRenewalCharge,
        private ReopenCustomerServiceCharge $reopenCustomerServiceCharge,
    ) {}

    /**
     * Make a cancelled customer service active again. While its current period has not
     * ended, the dates stay and, unless told otherwise, the upcoming period's charge that
     * was cancelled is reopened. Once the period has ended, a new start date (not before
     * the previous end date) is required and the first period's charge is created as on
     * assignment, or reopened when that period's charge was cancelled; the time spent
     * cancelled is never charged. A free service gets no charge reopened or created. The renewal charge is created at once when due. A
     * service of an archived customer cannot be reactivated. Returns the reopened or
     * created charges; calling it on an active service does nothing.
     *
     * @param  array{start_date?: string, create_first_charge?: bool, reopen_upcoming_charge?: bool}  $attributes
     * @return Collection<int, Charge>
     *
     * @throws InvalidCustomerService
     */
    public function handle(CustomerService $customerService, array $attributes = []): Collection
    {
        return DB::transaction(function () use ($customerService, $attributes): Collection {
            $customerService = CustomerService::query()
                ->with('customer')
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if ($customerService->status === CustomerServiceStatus::Active) {
                return new Collection;
            }

            if ($customerService->customer->isArchived()) {
                throw InvalidCustomerService::customerArchived();
            }

            $reopenUpcomingCharge = ($attributes['reopen_upcoming_charge'] ?? true) && ! $customerService->is_free;
            $today = Carbon::today(config('app.timezone'))->toDateString();
            $periodEnded = $customerService->end_date->toDateString() <= $today;

            if ($periodEnded) {
                $this->restartPeriod($customerService, $attributes['start_date'] ?? null, $today);
            }

            $customerService->update([
                'status' => CustomerServiceStatus::Active,
                'cancelled_on' => null,
                'cancel_note' => null,
            ]);

            $charges = [];

            if ($periodEnded) {
                $charges[] = $this->firstPeriodCharge($customerService, $reopenUpcomingCharge, $attributes['create_first_charge'] ?? true);
            } elseif ($reopenUpcomingCharge) {
                $charges[] = $this->reopenCustomerServiceCharge->handle($customerService, $customerService->end_date->toDateString());
            }

            $charges[] = $this->createRenewalCharge->handle($customerService);

            return collect(array_values(array_filter($charges)));
        });
    }

    /**
     * @throws InvalidCustomerService
     */
    private function restartPeriod(CustomerService $customerService, ?string $startDate, string $today): void
    {
        if ($startDate === null) {
            throw InvalidCustomerService::startDateRequired();
        }

        $start = CarbonImmutable::parse($startDate);

        if ($start->toDateString() < $customerService->end_date->toDateString()) {
            throw InvalidCustomerService::startDateBeforePreviousPeriodEnd();
        }

        $end = $start->addMonthsNoOverflow($customerService->period_months);

        if ($end->toDateString() <= $today) {
            throw InvalidCustomerService::periodAlreadyEnded();
        }

        $customerService->update([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    private function firstPeriodCharge(CustomerService $customerService, bool $reopenUpcomingCharge, bool $createFirstCharge): ?Charge
    {
        $periodStart = $customerService->start_date->toDateString();

        if ($customerService->charges()->whereDate('period_start', $periodStart)->exists()) {
            return $reopenUpcomingCharge
                ? $this->reopenCustomerServiceCharge->handle($customerService, $periodStart)
                : null;
        }

        if ($customerService->is_free || ! $createFirstCharge) {
            return null;
        }

        return $this->createCharge->handle($customerService->customer, [
            'customer_service_id' => $customerService->id,
            'description' => $customerService->name.' '.$customerService->start_date->format('Y').'–'.$customerService->end_date->format('Y'),
            'amount' => $customerService->price,
            'currency' => $customerService->currency,
            'due_date' => $periodStart,
            'period_start' => $periodStart,
            'period_end' => $customerService->end_date->toDateString(),
        ]);
    }
}
