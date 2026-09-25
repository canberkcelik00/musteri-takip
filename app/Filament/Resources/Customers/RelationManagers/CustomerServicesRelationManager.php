<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\CustomerServiceStatus;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\CustomerService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's services, one row each with its dates, price and status. Read-only;
 * assigning and changing services comes with G-4.5 and G-4.6.
 */
class CustomerServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerServices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('customer_services.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByRaw('status = ? desc', [CustomerServiceStatus::Active->value])
                ->orderBy('end_date')
                ->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('customer_services.name')),
                TextColumn::make('start_date')
                    ->label(__('customer_services.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('customer_services.end_date'))
                    ->date(),
                MoneyColumn::make('price')
                    ->label(__('customer_services.price'))
                    ->state(fn (CustomerService $record): int|string => $record->is_free ? __('customer_services.free') : $record->price),
                TextColumn::make('status')
                    ->label(__('customer_services.status'))
                    ->badge()
                    ->color(fn (CustomerServiceStatus $state): string => $state === CustomerServiceStatus::Active ? 'success' : 'gray'),
            ]);
    }
}
