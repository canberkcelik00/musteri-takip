<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Livewire\TopbarActions;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'short_name';

    public static function getModelLabel(): string
    {
        return __('customers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('customers.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    /**
     * Attributes Filament checks to decide the resource is globally searchable at all; the
     * actual query is Customer::search() (see getGlobalSearchEloquentQuery() below), which
     * also reaches contacts and domain names (docs/UI.md §2 Kural 12, §7).
     *
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['short_name', 'legal_title'];
    }

    /**
     * @param  Builder<Customer>  $query
     */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        $query->search($search);
    }

    /**
     * @return Builder<Customer>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return Customer::query()
            ->with('customerServices.items')
            ->orderBy('short_name');
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Customer $record */
        $details = array_filter([
            __('customers.legal_title') => $record->legal_title,
        ]);

        $domains = $record->domains();

        if ($domains !== []) {
            $details[__('customers.domains')] = implode(', ', array_slice($domains, 0, 3));
        }

        if ($record->isArchived()) {
            $details[__('archive.status')] = __('archive.status_archived');
        }

        return $details;
    }

    /**
     * "Ödeme al" next to the result, opening the topbar's payment form with this customer
     * pre-selected (Ctrl+K + yazma + 1, docs/UI.md §7) instead of the modal-in-a-modal a
     * result-specific RecordPaymentAction would need.
     *
     * @return array<Action>
     */
    public static function getGlobalSearchResultActions(Model $record): array
    {
        /** @var Customer $record */
        if ($record->isArchived()) {
            return [];
        }

        return [
            Action::make('recordPayment')
                ->label(__('payments.record'))
                ->icon(Heroicon::Plus)
                ->dispatch(TopbarActions::OPEN_RECORD_PAYMENT_EVENT, ['customer' => $record->getKey()]),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            CustomerServicesRelationManager::class,
            ChargesRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
