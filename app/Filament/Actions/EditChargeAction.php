<?php

namespace App\Filament\Actions;

use App\Actions\Charges\UpdateCharge;
use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Charge;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Changes the amount and due date of an open charge through UpdateCharge. When the charge has
 * allocations the amount field is locked and only the due date is sent.
 */
class EditChargeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editCharge';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('charges.edit'))
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(__('charges.edit_heading'))
            ->slideOver()
            ->visible(fn (Charge $record): bool => $record->status === ChargeStatus::Open)
            ->fillForm(fn (Charge $record): array => [
                'amount' => $record->amount,
                'due_date' => $record->due_date->toDateString(),
            ])
            ->schema(fn (Charge $record): array => [
                MoneyInput::make('amount')
                    ->label(__('charges.amount'))
                    ->required()
                    ->disabled($record->allocatedAmount() > 0)
                    ->helperText($record->allocatedAmount() > 0 ? __('charges.has_allocations') : null)
                    ->suffix($record->currency->getLabel()),
                DatePicker::make('due_date')
                    ->label(__('charges.due_date'))
                    ->required(),
            ])
            ->action(function (array $data, Charge $record, Action $action, UpdateCharge $updateCharge, Component $livewire): void {
                $attributes = ['due_date' => $data['due_date']];

                if ($record->allocatedAmount() === 0) {
                    $attributes['amount'] = $data['amount'];
                }

                try {
                    $updateCharge->handle($record, $attributes);
                } catch (InvalidCharge $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                $action->successNotificationTitle(__('charges.updated'))->success();

                $livewire->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT);
            });
    }
}
