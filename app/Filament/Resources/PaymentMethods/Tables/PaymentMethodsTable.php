<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\PaymentMethod;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['withInUse']))
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
            ])
            ->emptyStateHeading(__('payment_methods.empty_heading'))
            ->emptyStateDescription(__('payment_methods.empty_description'))
            ->emptyStateActions([
                CreateAction::make('createEmpty')->label(__('payment_methods.add'))->color('gray'),
            ]);
    }
}
