<?php

namespace App\Filament\Actions;

use App\Actions\Charges\CancelCharge;
use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Filament\Support\FieldError;
use App\Models\Charge;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Cancels an open charge without allocations through CancelCharge; the reason is required.
 * Hidden for a charge that already has allocations.
 */
class CancelChargeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelCharge';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('charges.cancel'))
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading(__('charges.cancel_heading'))
            ->modalSubmitActionLabel(__('charges.cancel'))
            ->visible(fn (Charge $record): bool => $record->status === ChargeStatus::Open && $record->allocatedAmount() === 0)
            ->schema([
                Textarea::make('reason')
                    ->label(__('charges.cancel_reason'))
                    ->required(),
            ])
            ->action(function (array $data, Charge $record, Action $action, CancelCharge $cancelCharge, Component $livewire): void {
                try {
                    $cancelCharge->handle($record, $data['reason']);
                } catch (InvalidCharge $exception) {
                    FieldError::report($exception, $action, ['reason']);

                    return;
                }

                $action->successNotificationTitle(__('charges.cancelled'))->success();

                $livewire->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT);
            });
    }
}
