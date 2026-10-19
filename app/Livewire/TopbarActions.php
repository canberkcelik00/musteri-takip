<?php

namespace App\Livewire;

use App\Filament\Actions\CreateCustomerAction;
use App\Filament\Actions\RecordPaymentAction;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The topbar's "Ödeme al" and "Yeni müşteri" actions (docs/UI.md §3), rendered through the
 * GLOBAL_SEARCH_AFTER render hook so they sit next to the search box on every panel page. Only
 * calls the shared RecordPaymentAction / CreateCustomerAction forms, which in turn only call the
 * Actions in app/Actions; no business logic lives here.
 */
class TopbarActions extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Dispatched by a Ctrl+K search result's "Ödeme al" action (CustomerResource::
     * getGlobalSearchResultActions()); the global search component itself has no HasActions
     * to open the payment form, so it reaches this one through a browser event instead.
     */
    public const string OPEN_RECORD_PAYMENT_EVENT = 'open-record-payment';

    public function recordPaymentAction(): Action
    {
        return RecordPaymentAction::make()
            ->color('primary');
    }

    #[On(self::OPEN_RECORD_PAYMENT_EVENT)]
    public function openRecordPayment(int $customer): void
    {
        $this->mountAction('recordPayment', ['customer' => $customer]);
    }

    /**
     * Its own top-level method: mountAction() / callAction() resolve an action by name through
     * a method on the component.
     */
    public function createCustomerAction(): Action
    {
        return CreateCustomerAction::make()
            ->label(__('navigation.new_customer'))
            ->color('gray');
    }

    public function render(): View
    {
        return view('livewire.topbar-actions');
    }
}
