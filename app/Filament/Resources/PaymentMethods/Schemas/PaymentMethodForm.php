<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Actions\PaymentMethods\DeletePaymentMethod;
use App\Filament\Actions\ArchivePaymentMethodAction;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Support\RecordActionsSection;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('payment_methods.name'))
                    ->required()
                    ->maxLength(255),
                RecordActionsSection::make(
                    __('payment_methods.actions_section'),
                    [ArchivePaymentMethodAction::make(), UnarchiveAction::make()],
                    [DeleteRecordAction::make()->deleteWith(DeletePaymentMethod::class)],
                ),
            ]);
    }
}
