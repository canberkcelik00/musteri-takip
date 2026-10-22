<?php

namespace App\Filament\Resources\CatalogServices\Tables;

use App\Enums\ServiceType;
use App\Filament\Actions\CreateQuoteAction;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\CatalogService;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The catalog list. Responsive (T-68/T-69 pattern, G-4B.7, no horizontal scroll from 320 px):
 * name and price always show, the name wraps and, at every width, opens the edit window when
 * clicked (a column action of its own name: the same name as the row's "Düzenle" would collide
 * in the table's action cache, T-69); it is drawn as a link so it reads as clickable. Type and
 * archive badge show from `md`; below `md` the row's "Düzenle" button is hidden, the name is
 * the one way in.
 */
class CatalogServicesTable
{
    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['withInUse']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('catalog_services.name'))
                    ->wrap()
                    ->extraAttributes(['class' => 'fi-ta-name-link'])
                    ->action(EditAction::make('editCatalogService'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('catalog_services.type'))
                    ->sortable()
                    ->visibleFrom('md'),
                MoneyColumn::make('price')
                    ->label(__('catalog_services.price'))
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (CatalogService $record): ?string => $record->isArchived() ? __('archive.status_archived') : null)
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('catalog_services.type'))
                    ->options(ServiceType::class),
                ArchivedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
                CreateQuoteAction::make(),
            ])
            ->emptyStateHeading(__('catalog_services.empty_heading'))
            ->emptyStateDescription(__('catalog_services.empty_description'))
            ->emptyStateActions([
                CreateAction::make('createEmpty')->label(__('catalog_services.add'))->color('gray'),
            ]);
    }
}
