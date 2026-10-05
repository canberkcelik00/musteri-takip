<?php

namespace App\Filament\Actions;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\SuggestPaymentAllocations;
use App\Actions\Payments\SuggestPaymentDefaults;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidPaymentAllocation;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Providers\AppServiceProvider;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Component;

/**
 * The payment form: customer, amount, currency, date, method and note, plus an optional
 * allocation preview whose rows can be changed. It only calls RecordPayment; the allocation
 * rules live in app/Actions/Payments. On the customer card the customer is fixed with customer().
 */
class RecordPaymentAction extends Action
{
    public const string PAYMENTS_CHANGED_EVENT = 'payments-changed';

    protected ?Customer $customer = null;

    public static function getDefaultName(): ?string
    {
        return 'recordPayment';
    }

    /**
     * Fix the customer (the customer card); the customer field is then left out of the form.
     */
    public function customer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('payments.record'))
            ->icon(Heroicon::Plus)
            ->modalHeading(__('payments.record_heading'))
            ->fillForm(fn (): array => $this->initialState())
            ->schema([
                Select::make('customer_id')
                    ->label(__('payments.customer'))
                    ->options(fn (): array => Customer::query()->notArchived()->orderBy('short_name')->pluck('short_name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->customer === null)
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        $defaults = app(SuggestPaymentDefaults::class)->handle($this->customerFrom($get));

                        $set('currency', $defaults['currency']->value);
                        $set('payment_method_id', $defaults['payment_method_id']);
                        $this->refreshAllocations($get, $set);
                    })
                    ->columnSpanFull(),
                MoneyInput::make('amount')
                    ->label(__('payments.amount'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->refreshAllocations($get, $set))
                    ->currencyField(),
                CurrencySelect::make('currency')
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->refreshAllocations($get, $set)),
                DatePicker::make('paid_on')
                    ->label(__('payments.paid_on'))
                    ->required(),
                Select::make('payment_method_id')
                    ->label(__('payments.payment_method'))
                    ->options(fn (): array => PaymentMethod::query()->notArchived()->orderBy('sort_order')->orderBy('id')->pluck('name', 'id')->all())
                    ->required(),
                Textarea::make('note')
                    ->label(__('payments.note'))
                    ->columnSpanFull(),
                Toggle::make('show_allocations')
                    ->label(__('payments.show_allocations'))
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->refreshAllocations($get, $set))
                    ->columnSpanFull(),
                Group::make()
                    ->schema(fn (Get $get): array => $this->allocationFields($get))
                    ->visible(fn (Get $get): bool => (bool) $get('show_allocations'))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Action $action, RecordPayment $recordPayment, Component $livewire): void {
                $customer = $this->customer ?? Customer::query()->findOrFail($data['customer_id']);
                $currency = $data['currency'] instanceof Currency ? $data['currency'] : Currency::from($data['currency']);

                $manualAllocations = ($data['show_allocations'] ?? false)
                    ? $this->manualAllocations($data['allocations'] ?? [])
                    : [];

                try {
                    $payment = $recordPayment->handle($customer, [
                        'amount' => $data['amount'],
                        'currency' => $currency,
                        'paid_on' => $data['paid_on'],
                        'payment_method_id' => (int) $data['payment_method_id'],
                        'note' => $data['note'] ?? null,
                    ], $manualAllocations);
                } catch (InvalidPaymentAllocation $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                $allocated = (int) $payment->allocations->sum('amount');

                $action->successNotificationTitle($this->resultMessage($allocated, $payment->amount - $allocated, $currency))->success();

                $livewire->dispatch(self::PAYMENTS_CHANGED_EVENT);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function initialState(): array
    {
        $customer = $this->customer ?? $this->prefilledCustomer();
        $defaults = app(SuggestPaymentDefaults::class)->handle($customer);

        return [
            'customer_id' => $customer?->getKey(),
            'currency' => ($this->prefilledCurrency() ?? $defaults['currency'])->value,
            'payment_method_id' => $defaults['payment_method_id'],
            'paid_on' => today()->toDateString(),
            'show_allocations' => false,
            'allocations' => [],
        ];
    }

    /**
     * The customer a Ctrl+K search result's or the customer list row's "Ödeme al" opened this
     * form for (docs/UI.md §7, T-66): selected but still changeable, unlike customer(), which
     * hides the field entirely. The `customer` argument (Ctrl+K, "Bugün") wins over the table
     * row's record (the customer list, or a charge row's customer), since an argument is always
     * explicit.
     */
    private function prefilledCustomer(): ?Customer
    {
        $customerId = $this->getArguments()['customer'] ?? null;

        if (filled($customerId)) {
            return Customer::query()->find($customerId);
        }

        $record = $this->getRecord();

        if ($record instanceof Charge) {
            return $record->customer;
        }

        return $record instanceof Customer ? $record : null;
    }

    /**
     * The currency the "Bugün" page's row "Ödeme al" opened this form for (docs/UI.md §5), or the
     * charge row's own currency in the charge lists: still changeable like the prefilled customer
     * above.
     */
    private function prefilledCurrency(): ?Currency
    {
        $currency = $this->getArguments()['currency'] ?? null;

        if ($currency === null && ($record = $this->getRecord()) instanceof Charge) {
            return $record->currency;
        }

        return $currency instanceof Currency ? $currency : Currency::tryFrom((string) $currency);
    }

    private function customerFrom(Get $get): ?Customer
    {
        if ($this->customer !== null) {
            return $this->customer;
        }

        $customerId = $get('customer_id');

        return filled($customerId) ? Customer::query()->find($customerId) : null;
    }

    private function currencyFrom(Get $get): Currency
    {
        $currency = $get('currency');

        return $currency instanceof Currency ? $currency : (Currency::tryFrom((string) $currency) ?? Currency::Try);
    }

    private function amountFrom(mixed $state): ?int
    {
        if (blank($state)) {
            return null;
        }

        try {
            return Money::parseAmount((string) $state);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The customer's open charges in the currency that still have an amount to pay, oldest due date first.
     *
     * @return Collection<int, Charge>
     */
    private function openCharges(Customer $customer, Currency $currency): Collection
    {
        return $customer->charges()
            ->where('currency', $currency)
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Charge $charge): bool => $charge->remainingAmount() > 0)
            ->values();
    }

    /**
     * @param  array<array-key, mixed>  $rows  Form state keyed by charge id.
     * @return array<int, int>
     */
    private function manualAllocations(array $rows): array
    {
        $allocations = [];

        foreach ($rows as $chargeId => $state) {
            $amount = is_int($state) ? $state : $this->amountFrom($state);

            if ($amount !== null && $amount > 0) {
                $allocations[(int) $chargeId] = $amount;
            }
        }

        return $allocations;
    }

    /**
     * @return list<Text|MoneyInput>
     */
    private function allocationFields(Get $get): array
    {
        $customer = $this->customerFrom($get);

        if ($customer === null) {
            return [];
        }

        $currency = $this->currencyFrom($get);
        $charges = $this->openCharges($customer, $currency);

        if ($charges->isEmpty()) {
            return [Text::make(__('payments.no_open_charges'))->key('no_open_charges')];
        }

        $fields = [Text::make(__('payments.allocations_helper'))->key('allocations_helper')];

        foreach ($charges as $charge) {
            $fields[] = MoneyInput::make("allocations.{$charge->id}")
                ->label(__('payments.allocation_label', [
                    'description' => $charge->description ?? __('payments.allocation_fallback_description'),
                    'due_date' => $charge->due_date->format(AppServiceProvider::DATE_FORMAT),
                    'remaining' => (new Money($charge->remainingAmount(), $currency))->format(),
                ]))
                ->live(onBlur: true)
                ->suffix($currency->getLabel());
        }

        $fields[] = Text::make(fn (Get $get): string => $this->previewSummary($get))->key('allocations_summary');

        return $fields;
    }

    /**
     * Fill the allocation rows with the automatic split of the current amount.
     */
    private function refreshAllocations(Get $get, Set $set): void
    {
        if (! $get('show_allocations')) {
            return;
        }

        $customer = $this->customerFrom($get);

        if ($customer === null) {
            $set('allocations', []);

            return;
        }

        $currency = $this->currencyFrom($get);
        $amount = $this->amountFrom($get('amount'));

        try {
            $suggested = $amount === null || $amount <= 0
                ? []
                : app(SuggestPaymentAllocations::class)->handle($customer, $currency, $amount);
        } catch (InvalidPaymentAllocation) {
            $suggested = [];
        }

        $state = [];

        foreach ($this->openCharges($customer, $currency) as $charge) {
            $state[$charge->id] = isset($suggested[$charge->id]) ? Money::formatAmount($suggested[$charge->id]) : null;
        }

        $set('allocations', $state);
    }

    /**
     * The result the payment would have with the rows as they are now, or why it would be refused.
     */
    private function previewSummary(Get $get): string
    {
        $customer = $this->customerFrom($get);
        $amount = $this->amountFrom($get('amount'));

        if ($customer === null || $amount === null || $amount <= 0) {
            return '';
        }

        $currency = $this->currencyFrom($get);

        try {
            $allocations = app(SuggestPaymentAllocations::class)->handle(
                $customer,
                $currency,
                $amount,
                $this->manualAllocations($get('allocations') ?? []),
            );
        } catch (InvalidPaymentAllocation $exception) {
            return $exception->getMessage();
        }

        $allocated = array_sum($allocations);

        return $this->resultMessage($allocated, $amount - $allocated, $currency);
    }

    /**
     * "1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi." with only the parts that apply.
     */
    private function resultMessage(int $allocated, int $credit, Currency $currency): string
    {
        $closed = $allocated > 0
            ? __('payments.result_closed', ['amount' => (new Money($allocated, $currency))->format()])
            : null;
        $added = $credit > 0
            ? __('payments.result_credit', ['amount' => (new Money($credit, $currency))->format()])
            : null;

        if ($closed !== null && $added !== null) {
            return __('payments.result_both', ['closed' => $closed, 'credit' => $added]);
        }

        return __('payments.result_single', ['part' => $closed ?? $added ?? '']);
    }
}
