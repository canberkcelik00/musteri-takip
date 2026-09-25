<?php

namespace App\Filament\Resources\CatalogServices\Tables;

use App\Enums\ServiceType;
use App\Filament\Actions\ArchiveAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\CatalogService;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CatalogServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('catalog_services.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('catalog_services.type'))
                    ->badge()
                    ->sortable(),
                MoneyColumn::make('price')
                    ->label(__('catalog_services.price'))
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (CatalogService $record): ?string => $record->isArchived() ? __('archive.status_archived') : null),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('catalog_services.type'))
                    ->options(ServiceType::class),
                ArchivedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ArchiveAction::make(),
                UnarchiveAction::make(),
            ]);
    }
}
