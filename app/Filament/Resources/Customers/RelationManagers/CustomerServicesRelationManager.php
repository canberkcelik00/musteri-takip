<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\CustomerServices\DeleteCustomerService;
use App\Actions\CustomerServices\MarkCustomerServiceFree;
use App\Actions\CustomerServices\PreviewCustomerServiceCancellation;
use App\Actions\CustomerServices\ReactivateCustomerService;
use App\Actions\CustomerServices\RemoveCustomerServiceFreeFlag;
use App\Actions\CustomerServices\ReopenCustomerServiceCharge;
use App\Actions\CustomerServices\UpdateCustomerService;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Package;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The customer's services, one row each with its dates, price and status. The "Hizmet ekle"
 * action assigns a catalog service or package through AssignCustomerService; the row actions
 * edit, mark free or paid, cancel and reactivate a service. Each only calls the matching
 * Action in app/Actions/CustomerServices; the rules live there.
 */
class CustomerServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerServices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('customer_services.plural');
    }

    /**
     * Filament makes relation managers read-only on view pages; the assign and row actions live on the customer card.
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
                ->scopes(['withInUse'])
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
                    ->badge(fn (CustomerService $record): bool => $record->status === CustomerServiceStatus::Cancelled || $record->isEndingSoon())
                    ->state(fn (CustomerService $record): string => $record->status === CustomerServiceStatus::Active && $record->isEndingSoon()
                        ? __('customer_services.ending_soon')
                        : $record->status->getLabel())
                    ->color(fn (CustomerService $record): ?string => match (true) {
                        $record->status === CustomerServiceStatus::Cancelled => 'gray',
                        $record->isEndingSoon() => 'warning',
                        default => null,
                    }),
            ])
            ->headerActions([
                $this->assignAction(),
            ])
            ->recordActions([
                $this->editAction(),
                $this->reactivateAction(),
                ActionGroup::make([
                    $this->markFreeAction(),
                    $this->removeFreeAction(),
                    $this->cancelAction(),
                    DeleteRecordAction::make()->deleteWith(DeleteCustomerService::class),
                ]),
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

    private function editAction(): Action
    {
        return Action::make('editService')
            ->label(__('customer_services.edit'))
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(__('customer_services.edit_heading'))
            ->slideOver()
            ->fillForm(fn (CustomerService $record): array => [
                'price' => $record->price,
                'currency' => $record->currency,
                'start_date' => $record->start_date->toDateString(),
                'end_date' => $record->end_date->toDateString(),
                'notes' => $record->notes,
                'items' => $record->items->mapWithKeys(fn (CustomerServiceItem $item): array => [
                    $item->id => ['details' => $item->details ?? [], 'license_key' => $item->license_key],
                ])->all(),
            ])
            ->schema(fn (CustomerService $record): array => $this->editFields($record))
            ->action(function (array $data, CustomerService $record, Action $action, UpdateCustomerService $updateCustomerService): void {
                $attributes = ['notes' => $data['notes'] ?? null, 'items' => $data['items'] ?? []];

                foreach (['price', 'start_date', 'end_date'] as $key) {
                    if (array_key_exists($key, $data)) {
                        $attributes[$key] = $data[$key];
                    }
                }

                if (array_key_exists('currency', $data)) {
                    $attributes['currency'] = $data['currency'] instanceof Currency ? $data['currency'] : Currency::from($data['currency']);
                }

                try {
                    $updateCustomerService->handle($record, $attributes);
                } catch (InvalidCustomerService $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                $action->successNotificationTitle(__('customer_services.updated'))->success();
            });
    }

    /**
     * Price and dates are locked for a cancelled service; notes and item details stay editable.
     *
     * @return list<Component>
     */
    private function editFields(CustomerService $record): array
    {
        $locked = $record->status === CustomerServiceStatus::Cancelled;

        $fields = [
            MoneyInput::make('price')
                ->label(__('customer_services.price'))
                ->required()
                ->disabled($locked)
                ->currencyField(),
            CurrencySelect::make('currency')
                ->disabled($locked),
            DatePicker::make('start_date')
                ->label(__('customer_services.start_date'))
                ->required()
                ->disabled($locked),
            DatePicker::make('end_date')
                ->label(__('customer_services.end_date'))
                ->required()
                ->disabled($locked),
            Textarea::make('notes')
                ->label(__('customer_services.notes'))
                ->columnSpanFull(),
        ];

        if ($locked) {
            array_unshift($fields, Text::make(__('customer_services.cancelled_locked_helper'))->columnSpanFull());
        }

        $sections = $record->items
            ->map(fn (CustomerServiceItem $item): Section => Section::make(__('customer_services.items_section', [
                'name' => $item->name,
                'type' => $item->type->getLabel(),
            ]))
                ->columns(2)
                ->schema($this->editItemFields($item)))
            ->all();

        return [...$fields, Group::make($sections)->columnSpanFull()];
    }

    /**
     * @return list<TextInput>
     */
    private function editItemFields(CustomerServiceItem $item): array
    {
        $fields = array_map(
            fn (string $key): TextInput => TextInput::make("items.{$item->id}.details.{$key}")
                ->label($item->type->customerDetailLabel($key))
                ->maxLength(255),
            $item->type->customerDetailKeys(),
        );

        if ($item->type === ServiceType::License) {
            $fields[] = TextInput::make("items.{$item->id}.license_key")
                ->label(__('customer_services.license_key'))
                ->maxLength(255);
        }

        return $fields;
    }

    private function markFreeAction(): Action
    {
        return Action::make('markFree')
            ->label(__('customer_services.mark_free'))
            ->icon(Heroicon::Gift)
            ->modalHeading(__('customer_services.mark_free_heading'))
            ->requiresConfirmation()
            ->visible(fn (CustomerService $record): bool => $record->status === CustomerServiceStatus::Active && ! $record->is_free)
            ->schema(fn (CustomerService $record): array => $this->cancellationFields($record))
            ->action(function (array $data, CustomerService $record, Action $action, MarkCustomerServiceFree $markCustomerServiceFree): void {
                $markCustomerServiceFree->handle($record, (bool) ($data['cancel_upcoming_charges'] ?? false));

                $action->successNotificationTitle(__('customer_services.marked_free'))->success();
            });
    }

    private function removeFreeAction(): Action
    {
        return Action::make('removeFree')
            ->label(__('customer_services.remove_free'))
            ->icon(Heroicon::CurrencyDollar)
            ->modalHeading(__('customer_services.remove_free_heading'))
            ->visible(fn (CustomerService $record): bool => $record->status === CustomerServiceStatus::Active && $record->is_free)
            ->fillForm(fn (CustomerService $record): array => ['price' => $record->price])
            ->schema(fn (CustomerService $record): array => [
                MoneyInput::make('price')
                    ->label(__('customer_services.new_price'))
                    ->required()
                    ->suffix($record->currency->getLabel()),
            ])
            ->action(function (array $data, CustomerService $record, Action $action, RemoveCustomerServiceFreeFlag $removeCustomerServiceFreeFlag): void {
                $charge = null;

                try {
                    $charge = $removeCustomerServiceFreeFlag->handle($record, $data['price']);
                } catch (InvalidCustomerService $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                $action->successNotificationTitle($charge === null
                    ? __('customer_services.removed_free')
                    : __('customer_services.removed_free_charge', ['amount' => (new Money($charge->amount, $charge->currency))->format()]))->success();
            });
    }

    private function cancelAction(): Action
    {
        return Action::make('cancelService')
            ->label(__('customer_services.cancel'))
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading(__('customer_services.cancel_heading'))
            ->modalSubmitActionLabel(__('customer_services.cancel'))
            ->visible(fn (CustomerService $record): bool => $record->status === CustomerServiceStatus::Active)
            ->fillForm(fn (): array => ['cancelled_on' => today()->toDateString(), 'cancel_upcoming_charges' => true])
            ->schema(fn (CustomerService $record): array => [
                DatePicker::make('cancelled_on')
                    ->label(__('customer_services.cancelled_on'))
                    ->required(),
                Textarea::make('cancel_note')
                    ->label(__('customer_services.cancel_note')),
                ...$this->cancellationFields($record),
            ])
            ->action(function (array $data, CustomerService $record, Action $action, CancelCustomerService $cancelCustomerService): void {
                $cancelCustomerService->handle(
                    $record,
                    $data['cancelled_on'],
                    $data['cancel_note'] ?? null,
                    (bool) ($data['cancel_upcoming_charges'] ?? false),
                );

                $action->successNotificationTitle(__('customer_services.cancelled'))->success();
            });
    }

    /**
     * The checked "cancel the upcoming period's charge" box, shown only when there is such a
     * charge, and what stays: overdue and other open charges are never cancelled here.
     *
     * @return list<Component>
     */
    private function cancellationFields(CustomerService $record): array
    {
        $preview = app(PreviewCustomerServiceCancellation::class)->handle($record);

        $fields = [];

        if ($preview['cancellable']->isNotEmpty()) {
            $fields[] = Checkbox::make('cancel_upcoming_charges')
                ->label(__('customer_services.cancel_upcoming_charges', ['amount' => $this->totalOf($preview['cancellable'])]))
                ->default(true);
        }

        if ($preview['overdue']->isNotEmpty()) {
            $fields[] = Text::make(__('customer_services.stays_overdue', ['amount' => $this->totalOf($preview['overdue'])]))->key('stays_overdue');
        }

        if ($preview['open']->isNotEmpty()) {
            $fields[] = Text::make(__('customer_services.stays_open', ['amount' => $this->totalOf($preview['open'])]))->key('stays_open');
        }

        return $fields;
    }

    /**
     * @param  Collection<int, Charge>  $charges
     */
    private function totalOf(Collection $charges): string
    {
        return Money::formatTotals(Money::sumByCurrency(
            $charges->map(fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency)),
        ));
    }

    private function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label(__('customer_services.reactivate'))
            ->icon(Heroicon::ArrowPath)
            ->modalHeading(__('customer_services.reactivate_heading'))
            ->visible(fn (CustomerService $record): bool => $record->status === CustomerServiceStatus::Cancelled)
            ->fillForm(fn (): array => ['start_date' => today()->toDateString(), 'create_first_charge' => 1, 'reopen_upcoming_charge' => true])
            ->schema(fn (CustomerService $record): array => $this->reactivateFields($record))
            ->action(function (array $data, CustomerService $record, Action $action, ReactivateCustomerService $reactivateCustomerService): void {
                $attributes = [
                    'reopen_upcoming_charge' => (bool) ($data['reopen_upcoming_charge'] ?? false),
                    'create_first_charge' => (bool) ($data['create_first_charge'] ?? true),
                ];

                if (isset($data['start_date'])) {
                    $attributes['start_date'] = $data['start_date'];
                }

                try {
                    $reactivateCustomerService->handle($record, $attributes);
                } catch (InvalidCustomerService $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                $action->successNotificationTitle(__('customer_services.reactivated'))->success();
            });
    }

    /**
     * While the period has not ended the dates stay; once it has, a new start date is asked
     * and, unless a charge for that period already exists, whether to create the first charge.
     * The cancelled charge of the period can be reopened when there is one.
     *
     * @return list<Component>
     */
    private function reactivateFields(CustomerService $record): array
    {
        $periodEnded = $record->end_date->toDateString() <= Carbon::today(config('app.timezone'))->toDateString();

        $periodStart = function (Get $get) use ($record, $periodEnded): ?string {
            if (! $periodEnded) {
                return $record->end_date->toDateString();
            }

            $startDate = $get('start_date');

            return filled($startDate) ? Carbon::parse($startDate)->toDateString() : null;
        };

        $fields = [];

        if ($record->customer->isArchived()) {
            $fields[] = Text::make(__('customer_services.customer_archived'))->key('customer_archived');
        }

        if ($periodEnded) {
            $fields[] = Text::make(__('customer_services.reactivate_period_ended_helper'));
            $fields[] = DatePicker::make('start_date')
                ->label(__('customer_services.reactivate_new_start_date'))
                ->required()
                ->live();
            $fields[] = Radio::make('create_first_charge')
                ->label(__('customer_services.create_first_charge'))
                ->options([
                    1 => __('customer_services.create_first_charge_yes'),
                    0 => __('customer_services.create_first_charge_no'),
                ])
                ->default(1)
                ->required()
                ->visible(fn (Get $get): bool => ! $record->is_free
                    && $periodStart($get) !== null
                    && $record->charges()->whereDate('period_start', $periodStart($get))->doesntExist());
        }

        $fields[] = Checkbox::make('reopen_upcoming_charge')
            ->label(__('customer_services.reopen_charge', ['amount' => (new Money($record->price, $record->currency))->format()]))
            ->default(true)
            ->visible(function (Get $get) use ($record, $periodStart): bool {
                $start = $periodStart($get);

                return ! $record->is_free
                    && $start !== null
                    && app(ReopenCustomerServiceCharge::class)->find($record, $start) !== null;
            });

        return $fields;
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
