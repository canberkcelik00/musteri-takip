<?php

namespace App\Livewire;

use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The topbar's "Ödeme al" and "Yeni" (Müşteri, Elle borç) actions (docs/UI.md §3), rendered
 * through the GLOBAL_SEARCH_AFTER render hook so they sit next to the search box on every
 * panel page. Only calls the shared RecordPaymentAction / CreateManualChargeAction forms, which
 * in turn only call the Actions in app/Actions; no business logic lives here.
 */
class TopbarActions extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function recordPaymentAction(): Action
    {
        return RecordPaymentAction::make()
            ->color('primary');
    }

    public function newCustomerAction(): Action
    {
        return Action::make('newCustomer')
            ->label(__('navigation.new_customer'))
            ->url(fn (): string => CustomerResource::getUrl('create'));
    }

    /**
     * Its own top-level method, not built inline in newAction(), so mountAction() /
     * callAction() can resolve it directly by name in tests, the same way they resolve any
     * other action on this component.
     */
    public function createManualChargeAction(): Action
    {
        return CreateManualChargeAction::make()
            ->label(__('navigation.new_manual_charge'));
    }

    public function newAction(): ActionGroup
    {
        return ActionGroup::make([
            $this->newCustomerAction(),
            $this->createManualChargeAction(),
        ])
            ->label(__('navigation.new'))
            ->button()
            ->color('gray');
    }

    public function render(): View
    {
        return view('livewire.topbar-actions');
    }
}
