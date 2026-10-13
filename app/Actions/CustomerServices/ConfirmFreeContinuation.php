<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;
use Illuminate\Support\Facades\DB;

class ConfirmFreeContinuation
{
    /**
     * Record that the user confirmed a free service continues into its upcoming period
     * ("Ücretsiz devam et", docs/UI.md §5, PRD "Ücretsiz hizmetler"), so it stops appearing
     * as a pending decision. Stores the service's current end_date; once the period advances
     * past it, the question returns for the new period automatically (no field to reset).
     * Does nothing for a service that is not active or not free, so calling it again on an
     * already-confirmed or since-changed service is harmless. Returns whether it recorded anything.
     */
    public function handle(CustomerService $customerService): bool
    {
        return DB::transaction(function () use ($customerService): bool {
            $customerService = CustomerService::query()
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if ($customerService->status !== CustomerServiceStatus::Active || ! $customerService->is_free) {
                return false;
            }

            $customerService->update([
                'free_continuation_confirmed_for' => $customerService->end_date,
            ]);

            return true;
        });
    }
}
