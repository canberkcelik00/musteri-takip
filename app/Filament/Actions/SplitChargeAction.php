<?php

namespace App\Filament\Actions;

use App\Actions\Charges\SplitCharge;
use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Charge;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Splits an amount off an open charge without allocations into a new charge through SplitCharge.
 * Hidden for a charge that already has allocations.
 */
class SplitChargeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'splitCharge';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('charges.split'))
            ->icon(Heroicon::ArrowsPointingOut)
            ->modalHeading(__('charges.split_heading'))
            ->modalSubmitActionLabel(__('charges.split'))
            ->visible(fn (Charge $record): bool => $record->status === ChargeStatus::Open && $record->allocatedAmount() === 0)
            ->fillForm(fn (Charge $record): array => ['due_date' => $record->due_date->toDateString()])
            ->schema(fn (Charge $record): array => [
                Text::make(__('charges.split_helper')),
                MoneyInput::make('amount')
                    ->label(__('charges.split_amount'))
                    ->required()
                    ->suffix($record->currency->getLabel()),
                DatePicker::make('due_date')
                    ->label(__('charges.split_due_date'))
                    ->required(),
                TextInput::make('description')
                    ->label(__('charges.split_description'))
                    ->placeholder($record->description)
                    ->maxLength(255),
            ])
            ->action(function (array $data, Charge $record, Action $action, SplitCharge $splitCharge, Component $livewire): void {
                try {
                    $splitCharge->handle($record, $data['amount'], $data['due_date'], $data['description'] ?? null);
                } catch (InvalidCharge $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                $action->successNotificationTitle(__('charges.split_done'))->success();

                $livewire->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT);
            });
    }
}
