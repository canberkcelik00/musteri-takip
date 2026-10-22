<?php

namespace App\Filament\Resources\Packages\Tables;

use App\Filament\Actions\CreateQuoteAction;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\Package;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The package list. Responsive (G-4B.7, no horizontal scroll from 320 px): name and price always
 * show, the name wraps; the included services and the archive badge show from `md`.
 */
class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['withInUse']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('packages.name'))
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('catalogServices.name')
                    ->label(__('packages.catalog_services'))
                    ->listWithLineBreaks()
                    ->visibleFrom('md'),
                MoneyColumn::make('price')
                    ->label(__('packages.price'))
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Package $record): ?string => $record->isArchived() ? __('archive.status_archived') : null)
                    ->visibleFrom('md'),
            ])
            ->filters([
                ArchivedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                CreateQuoteAction::make(),
            ])
            ->emptyStateHeading(__('packages.empty_heading'))
            ->emptyStateDescription(__('packages.empty_description'))
            ->emptyStateActions([
                CreateAction::make('createEmpty')->label(__('packages.add'))->color('gray'),
            ]);
    }
}
