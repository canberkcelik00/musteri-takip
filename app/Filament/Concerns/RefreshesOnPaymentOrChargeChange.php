<?php

namespace App\Filament\Concerns;

use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use Livewire\Attributes\On;

/**
 * Recording or deleting a payment, or changing a charge, can change what this component
 * shows (an open debt total, a charge's status) even when it happened through another Livewire
 * component (the topbar's "Ödeme al" / "Yeni" actions, G-4.13). The component just needs to
 * render again for the change to show; it does not need to run any logic itself.
 */
trait RefreshesOnPaymentOrChargeChange
{
    #[On(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)]
    #[On(EditChargeAction::CHARGES_CHANGED_EVENT)]
    public function refreshAfterPaymentOrChargeChange(): void {}
}
