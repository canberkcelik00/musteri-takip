<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\CustomerServices\DeleteCustomerService;
use App\Actions\CustomerServices\MarkCustomerServiceFree;
use App\Actions\CustomerServices\PreviewCustomerServiceCancellation;
use App\Actions\CustomerServices\ReactivateCustomerService;
use App\Actions\CustomerServices\ReopenCustomerServiceCharge;
use App\Actions\CustomerServices\UpdateCustomerService;
use App\Actions\Servers\BuildUsageReport;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Filament\Actions\AssignCustomerServiceAction;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Actions\RemoveFreeFlagAction;
use App\Filament\Actions\ViewUsageAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\FieldError;
use App\Filament\Support\MobileStatusBadge;
use App\Filament\Support\RecordActionsSection;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * The customer's active services, one row each with its dates, price and status; the service
 * name shows its distinct domain values (all items, joined with commas) as a faded line below,
 * from {@see CustomerService::domains()} (T-66; horizontal scroll avoided, no separate column).
 * {@see CancelledCustomerServicesRelationManager} extends this for the card's collapsed
 * "İptal edilenler" section. The row actions (edit, mark free or paid, cancel, reactivate) only
 * call the matching Action in app/Actions/CustomerServices; the rules live there. The row
 * has no "Düzenle" button (G-4B.5, as in the reference screen): clicking the name opens the edit
 * panel at every width; a cancelled service keeps "Yeniden aktif et" in its row (G-4.17). The rarer
 * actions live in the edit panel's "Hizmet işlemleri" section, with the dangerous ones (İptal et,
 * Sil) on a row of their own at its end. "Hizmet ekle"
 * (AssignCustomerServiceAction, T-66) is a page header action on the card, not a table action.
 * The active table's heading carries the "N aktif" count. Responsive (T-68/T-70/G-4B.5, no
 * horizontal scroll at any width, classic table columns kept intact at every width — no
 * {@see Stack}/`Split`, see {@see MobileStatusBadge}): name (+ domains) and price always show;
 * end date from `md`; the "Durum" column from `xl` (the card's left column is narrow), below
 * which the name's description carries a colored status badge instead.
 */
class CustomerServicesRelationManager extends RelationManager
{
    use RefreshesOnPaymentOrChargeChange;

    /**
     * Hides a row action's button below `md` (T-68); the "Düzenle" panel the name opens holds the
     * same option ("Hizmet işlemleri") at that width instead. `inline-flex` matches Filament's own
     * small table action display, so nothing shifts once it reappears at `md`.
     */
    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

    /**
     * The wrapper class of the status badge under the name: visible below `xl`, where the
     * "Durum" column is hidden (theme.css; the card's left column is narrow).
     */
    private const string BADGE_BELOW_XL = 'fi-badge-below-xl';

    protected static string $relationship = 'customerServices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('customer_services.plural');
    }

    /**
     * The status this table's rows are limited to; overridden by
     * {@see CancelledCustomerServicesRelationManager}.
     */
    protected function status(): CustomerServiceStatus
    {
        return CustomerServiceStatus::Active;
    }

    /**
     * Filament makes relation managers read-only on view pages; the row actions live on the customer card.
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
                ->with('items.server')
                ->where('status', $this->status())
                ->orderBy('end_date')
                ->orderBy('id'))
            ->paginated(false)
            ->description($this->status() === CustomerServiceStatus::Active
                ? fn (): string => __('customer_services.active_count', ['count' => $this->customerOwner()->customerServices()->where('status', CustomerServiceStatus::Active)->count()])
                : null)
            ->columns([
                TextColumn::make('name')
                    ->label(__('customer_services.name'))
                    ->wrap()
                    ->description(fn (CustomerService $record): ?Htmlable => $this->nameDescription($record))
                    ->action($this->editAction()),
                TextColumn::make('end_date')
                    ->label(__('customer_services.end_date'))
                    ->date()
                    ->visibleFrom('md'),
                MoneyColumn::make('price')
                    ->label(__('customer_services.price'))
                    ->state(fn (CustomerService $record): int|string => $record->is_free ? __('customer_services.free') : $record->price),
                TextColumn::make('status')
                    ->label(__('customer_services.status'))
                    ->visibleFrom('xl')
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
            ->recordActions([
                $this->reactivateAction()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ])
            ->emptyStateHeading(__('customer_services.empty_heading'))
            ->emptyStateDescription(__('customer_services.empty_description'))
            ->emptyStateActions($this->status() === CustomerServiceStatus::Active
                ? [AssignCustomerServiceAction::make('assignServiceEmpty')->customer($this->customerOwner())->color('gray')]
                : []);
    }

    private function customerOwner(): Customer
    {
        $customer = $this->getOwnerRecord();
        assert($customer instanceof Customer);

        return $customer;
    }

    /**
     * The name column's description (T-70, G-4B.5): the service's domains, plus — only below
     * `xl`, where the "Durum" column is hidden — a colored status badge, for a cancelled or
     * ending-soon service (a plain active one shows nothing, same as the column).
     */
    private function nameDescription(CustomerService $record): ?Htmlable
    {
        $domains = $record->domains();
        $others = count($record->otherDomains());
        $moreDomains = $domains !== [] && $others > 0
            ? ' +'.__('customer_services.more_domains', ['count' => $others])
            : '';

        $badge = match (true) {
            $record->status === CustomerServiceStatus::Cancelled => MobileStatusBadge::html($record->status->getLabel(), 'gray', self::BADGE_BELOW_XL),
            $record->isEndingSoon() => MobileStatusBadge::html(__('customer_services.ending_soon'), 'warning', self::BADGE_BELOW_XL),
            default => null,
        };

        $usageBadge = $this->usageBadge($record);

        if ($domains === [] && $badge === null && $usageBadge === null) {
            return null;
        }

        $html = view('filament.support.domain-links', ['domains' => $domains])->render().e($moreDomains);

        if ($badge !== null) {
            $html .= $badge->toHtml();
        }

        if ($usageBadge !== null) {
            $html .= $usageBadge;
        }

        return new HtmlString($html);
    }

    /**
     * The "Kullanım" window of the card's service row (G-9.4): opened by the badge that shows
     * only while an active service's linked hosting account is at or over the warning threshold.
     */
    public function viewUsageAction(): ViewUsageAction
    {
        return ViewUsageAction::make();
    }

    /**
     * The worst problem of the service's linked accounts as one clickable badge ("Disk %94"),
     * or nothing while every account is below the threshold.
     */
    private function usageBadge(CustomerService $record): ?string
    {
        if ($record->status !== CustomerServiceStatus::Active) {
            return null;
        }

        $worst = null;

        foreach ($record->items as $item) {
            $badge = app(BuildUsageReport::class)->handle($item)?->badge();

            if ($badge !== null && ($worst === null || $badge['level']->severity() > $worst['badge']['level']->severity())) {
                $worst = ['item' => $item, 'badge' => $badge];
            }
        }

        return $worst === null
            ? null
            : View::make('filament.support.usage-badge', [
                'itemId' => $worst['item']->id,
                'label' => $worst['badge']['text'],
                'color' => $worst['badge']['level']->getColor(),
            ])->render();
    }

    private function editAction(string $name = 'editService'): Action
    {
        return Action::make($name)
            ->label(__('customer_services.edit'))
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(__('customer_services.edit_heading'))
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
                    FieldError::report($exception, $action, ['price', 'start_date', 'end_date']);

                    return;
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

        return [
            ...$fields,
            Group::make($sections)->columnSpanFull(),
            RecordActionsSection::make(
                __('customer_services.actions_section'),
                [
                    $this->reactivateAction(),
                    $this->markFreeAction(),
                    RemoveFreeFlagAction::make(),
                ],
                [
                    $this->cancelAction(),
                    DeleteRecordAction::make()->deleteWith(DeleteCustomerService::class),
                ],
            ),
        ];
    }

    /**
     * The item's own fields; a hosting item linked to a server account also gets its one-line
     * usage summary and the "Kullanımı gör" button (G-9.4), after the fields.
     *
     * @return list<Component>
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

        if ($item->server_id !== null && $item->remote_key !== null) {
            $summary = app(BuildUsageReport::class)->handle($item)?->summary();

            if ($item->otherDomains() !== []) {
                $fields[] = Text::make(__('usage.other_domains', ['domains' => implode(', ', $item->otherDomains())]))
                    ->color('gray')
                    ->columnSpanFull();
            }

            $fields[] = Text::make(filled($summary) ? $summary : __('usage.summary_none'))
                ->color('gray')
                ->columnSpanFull();
            $fields[] = Actions::make([
                ViewUsageAction::make("viewUsage{$item->id}")->arguments(['item' => $item->id])->outlined(),
            ])->columnSpanFull();
        }

        return $fields;
    }

    private function markFreeAction(string $name = 'markFree'): Action
    {
        return Action::make($name)
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

    private function cancelAction(string $name = 'cancelService'): Action
    {
        return Action::make($name)
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

    private function reactivateAction(string $name = 'reactivate'): Action
    {
        return Action::make($name)
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
                    FieldError::report($exception, $action, ['start_date']);

                    return;
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
}
