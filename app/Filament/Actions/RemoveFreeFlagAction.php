<?php

namespace App\Filament\Actions;

use App\Actions\CustomerServices\RemoveCustomerServiceFreeFlag;
use App\Enums\CustomerServiceStatus;
use App\Exceptions\InvalidCustomerService;
use App\Filament\Concerns\ResolvesCustomerServiceArgument;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\FieldError;
use App\Models\CustomerService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * "Ücretli yap": makes a free service paid again through RemoveCustomerServiceFreeFlag.
 * Shared by the customer card's service row action (T-57, where the table supplies the
 * record) and the "Bugün" page's free-decision rows (G-4.15), which are not table rows and
 * instead pass a customerService argument (the same technique RecordPaymentAction uses for
 * the Ctrl+K "Ödeme al" shortcut, T-64) that {@see self::getRecord()} resolves.
 */
class RemoveFreeFlagAction extends Action
{
    use ResolvesCustomerServiceArgument;

    public static function getDefaultName(): ?string
    {
        return 'removeFree';
    }

    public function getRecord(bool $withDefault = true): CustomerService|array|null
    {
        if ($this->hasCustomerServiceArgument()) {
            return $this->customerServiceFromArguments();
        }

        return parent::getRecord($withDefault);
    }

    /**
     * Whether the service is still active and free. On the "Bugün" rows the button stays
     * visible for any service found (the list may be stale), so this is checked again when the
     * action runs and a warning replaces the work.
     */
    private function canRemoveFreeFlag(CustomerService $record): bool
    {
        return $record->status === CustomerServiceStatus::Active && $record->is_free;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('customer_services.remove_free'))
            ->icon(Heroicon::CurrencyDollar)
            ->modalHeading(__('customer_services.remove_free_heading'))
            ->visible(fn (?CustomerService $record): bool => $record !== null && ($this->hasCustomerServiceArgument() || $this->canRemoveFreeFlag($record)))
            ->fillForm(fn (?CustomerService $record): array => $record === null ? [] : ['price' => $record->price])
            ->schema(fn (?CustomerService $record): array => $record === null ? [] : [
                MoneyInput::make('price')
                    ->label(__('customer_services.new_price'))
                    ->required()
                    ->suffix($record->currency->getLabel()),
            ])
            ->action(function (array $data, CustomerService $record, Action $action, RemoveCustomerServiceFreeFlag $removeCustomerServiceFreeFlag, Component $livewire): void {
                $charge = null;

                if (! $this->canRemoveFreeFlag($record)) {
                    Notification::make()
                        ->warning()
                        ->title(__('customer_services.free_action_not_applied'))
                        ->send();

                    return;
                }

                try {
                    $charge = $removeCustomerServiceFreeFlag->handle($record, $data['price']);
                } catch (InvalidCustomerService $exception) {
                    FieldError::report($exception, $action, ['price']);

                    return;
                }

                $action->successNotificationTitle($charge === null
                    ? __('customer_services.removed_free')
                    : __('customer_services.removed_free_charge', ['amount' => (new Money($charge->amount, $charge->currency))->format()]))->success();

                if ($charge !== null) {
                    $livewire->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT);
                }
            });
    }
}
