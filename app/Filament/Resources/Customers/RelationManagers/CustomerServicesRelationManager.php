<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Actions\CustomerServices\AssignCustomerService;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The customer's services, one row each with its dates, price and status. The "Hizmet ekle"
 * action assigns a catalog service or package through AssignCustomerService; changing
 * services comes with G-4.6, so the rows themselves stay read-only.
 */
class CustomerServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerServices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('customer_services.plural');
    }

    /**
     * Filament makes relation managers read-only on view pages; the assign action lives on the customer card.
     */
    public function isReadOnly(): bool
    {
        return false;
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
            ])
            ->headerActions([
                $this->assignAction(),
            ]);
    }

    private function assignAction(): Action
    {
        return Action::make('assign')
            ->label(__('customer_services.assign'))
            ->modalHeading(__('customer_services.assign_heading'))
            ->modalSubmitActionLabel(__('customer_services.assign'))
            ->slideOver()
            ->schema([
                Select::make('sold')
                    ->label(__('customer_services.sold'))
                    ->options(fn (): array => $this->soldOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('items', []);

                        $sold = $this->resolveSold($state);

                        if ($sold === null) {
                            return;
                        }

                        $set('price', Money::formatAmount($sold->price));
                        $set('currency', $sold->currency->value);
                    })
                    ->columnSpanFull(),
                MoneyInput::make('price')
                    ->label(__('customer_services.price'))
                    ->required()
                    ->currencyField(),
                CurrencySelect::make('currency'),
                DatePicker::make('start_date')
                    ->label(__('customer_services.start_date'))
                    ->default(fn (): string => today()->toDateString())
                    ->required(),
                Toggle::make('is_free')
                    ->label(__('customer_services.is_free'))
                    ->live()
                    ->inline(false),
                Radio::make('create_first_charge')
                    ->label(__('customer_services.create_first_charge'))
                    ->helperText(__('customer_services.create_first_charge_helper'))
                    ->options([
                        1 => __('customer_services.create_first_charge_yes'),
                        0 => __('customer_services.create_first_charge_no'),
                    ])
                    ->default(1)
                    ->required()
                    ->visible(fn (Get $get): bool => ! $get('is_free'))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('customer_services.notes'))
                    ->columnSpanFull(),
                Group::make()
                    ->schema(fn (Get $get): array => $this->itemSections($this->resolveSold($get('sold'))))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action, AssignCustomerService $assignCustomerService): void {
                $customer = $this->getOwnerRecord();
                assert($customer instanceof Customer);

                $sold = $this->resolveSold($data['sold'] ?? null);
                assert($sold !== null);

                try {
                    $assignCustomerService->handle($customer, $sold, [
                        'start_date' => $data['start_date'],
                        'price' => $data['price'],
                        'currency' => $data['currency'] instanceof Currency ? $data['currency'] : Currency::from($data['currency']),
                        'is_free' => (bool) ($data['is_free'] ?? false),
                        'create_first_charge' => (bool) ($data['create_first_charge'] ?? true),
                        'notes' => $data['notes'] ?? null,
                        'items' => $data['items'] ?? [],
                    ]);
                } catch (InvalidCustomerService $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                $action->successNotificationTitle(__('customer_services.assigned'))->success();
            });
    }

    /**
     * Catalog services and packages in two groups; archived ones come last in their group.
     *
     * @return array<string, array<string, string>>
     */
    private function soldOptions(): array
    {
        return array_filter([
            __('customer_services.group_services') => $this->optionsFor(CatalogService::query(), 'catalog_service'),
            __('customer_services.group_packages') => $this->optionsFor(Package::query(), 'package'),
        ]);
    }

    /**
     * @param  Builder<CatalogService>|Builder<Package>  $query
     * @return array<string, string>
     */
    private function optionsFor(Builder $query, string $kind): array
    {
        return $query
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CatalogService|Package $record): array => [
                "{$kind}:{$record->id}" => $record->isArchived()
                    ? __('customer_services.archived_option', ['name' => $record->name])
                    : $record->name,
            ])
            ->all();
    }

    private function resolveSold(mixed $key): CatalogService|Package|null
    {
        [$kind, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

        return match ($kind) {
            'catalog_service' => CatalogService::query()->find($id),
            'package' => Package::query()->find($id),
            default => null,
        };
    }

    /**
     * One section per catalog service that gets an item, with the customer details of its type
     * and, for licenses, the license key (kept apart from the details keys).
     *
     * @return list<Section>
     */
    private function itemSections(CatalogService|Package|null $sold): array
    {
        if ($sold === null) {
            return [];
        }

        /** @var Collection<int, CatalogService> $catalogServices */
        $catalogServices = $sold instanceof Package
            ? $sold->catalogServices()->orderBy('catalog_services.id')->get()
            : collect([$sold]);

        return $catalogServices
            ->map(fn (CatalogService $catalogService): Section => Section::make(__('customer_services.items_section', [
                'name' => $catalogService->name,
                'type' => $catalogService->type->getLabel(),
            ]))
                ->columns(2)
                ->schema($this->itemFields($catalogService)))
            ->values()
            ->all();
    }

    /**
     * @return list<TextInput>
     */
    private function itemFields(CatalogService $catalogService): array
    {
        $type = $catalogService->type;

        $fields = array_map(
            fn (string $key): TextInput => TextInput::make("items.{$catalogService->id}.details.{$key}")
                ->label($type->customerDetailLabel($key))
                ->maxLength(255),
            $type->customerDetailKeys(),
        );

        if ($type === ServiceType::License) {
            $fields[] = TextInput::make("items.{$catalogService->id}.license_key")
                ->label(__('customer_services.license_key'))
                ->maxLength(255);
        }

        return $fields;
    }
}
