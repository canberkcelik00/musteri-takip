<?php

namespace App\Filament\Resources\Packages\Tables;

use App\Actions\Packages\DeletePackage;
use App\Filament\Actions\ArchiveAction;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\Package;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('catalogServices.name')
                    ->label(__('packages.catalog_services'))
                    ->listWithLineBreaks(),
                MoneyColumn::make('price')
                    ->label(__('packages.price'))
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Package $record): ?string => $record->isArchived() ? __('archive.status_archived') : null),
            ])
            ->filters([
                ArchivedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ArchiveAction::make(),
                UnarchiveAction::make(),
                DeleteRecordAction::make()->deleteWith(DeletePackage::class),
            ]);
    }
}
