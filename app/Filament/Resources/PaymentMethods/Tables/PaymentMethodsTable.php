<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Filament\Actions\ArchivePaymentMethodAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\PaymentMethod;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('payment_methods.name')),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (PaymentMethod $record): ?string => $record->isArchived() ? __('archive.status_archived') : null),
            ])
            ->filters([
                ArchivedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ArchivePaymentMethodAction::make(),
                UnarchiveAction::make(),
            ]);
    }
}
