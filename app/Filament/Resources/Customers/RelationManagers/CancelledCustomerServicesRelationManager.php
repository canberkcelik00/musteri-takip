<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\CustomerServiceStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer card's collapsed "İptal edilenler" section (T-66): the same table as
 * {@see CustomerServicesRelationManager}, limited to cancelled services. "Yeniden aktif et",
 * "Düzenle" and delete stay available on these rows.
 */
class CancelledCustomerServicesRelationManager extends CustomerServicesRelationManager
{
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        assert($ownerRecord instanceof Customer);

        return __('customers.cancelled_services', ['count' => $ownerRecord->customerServices()->where('status', CustomerServiceStatus::Cancelled)->count()]);
    }

    protected function status(): CustomerServiceStatus
    {
        return CustomerServiceStatus::Cancelled;
    }
}
