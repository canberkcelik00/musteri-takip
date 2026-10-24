<?php

namespace App\Livewire\Windows;

use App\Actions\Payments\RecordPayment as RecordPaymentAction;
use App\Actions\Payments\SuggestPaymentAllocations;
use App\Actions\Payments\SuggestPaymentDefaults;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidPaymentAllocation;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The payment window: customer, amount, currency, date, method and note, plus an optional allocation preview whose
 * rows can be changed. It only calls RecordPayment; the allocation rules live in app/Actions/Payments. On the
 * customer's card the customer is fixed (`fixed`), elsewhere it is chosen but still changeable.
 */
class RecordPayment extends Component
{
    public const string PAYMENTS_CHANGED_EVENT = 'payments-changed';

    public string $customer_id = '';

    #[Locked]
    public bool $fixed = false;

    public string $amount = '';

    public string $currency = 'TRY';

    public string $paid_on = '';

    public string $payment_method_id = '';

    public string $note = '';

    public bool $show_allocations = false;

    /** @var array<int|string, string> Typed amounts keyed by charge id. */
    public array $allocations = [];

    public function mount(?int $customer = null, ?string $currency = null, bool $fixed = false): void
    {
        $this->fixed = $fixed && $customer !== null;
        $this->customer_id = $customer === null ? '' : (string) $customer;

        $defaults = app(SuggestPaymentDefaults::class)->handle($this->customer());

        $this->currency = (Currency::tryFrom((string) $currency) ?? $defaults['currency'])->value;
        $this->payment_method_id = $defaults['payment_method_id'] === null ? '' : (string) $defaults['payment_method_id'];
        $this->paid_on = today()->toDateString();
    }

    public function updatedCustomerId(): void
    {
        if ($this->fixed) {
            return;
        }

        $defaults = app(SuggestPaymentDefaults::class)->handle($this->customer());

        $this->currency = $defaults['currency']->value;
        $this->payment_method_id = $defaults['payment_method_id'] === null ? '' : (string) $defaults['payment_method_id'];
        $this->refreshAllocations();
    }

    public function updatedAmount(): void
    {
        $this->refreshAllocations();
    }

    public function updatedCurrency(): void
    {
        $this->refreshAllocations();
    }

    public function updatedShowAllocations(): void
    {
        $this->refreshAllocations();
    }

    public function save(RecordPaymentAction $recordPayment): void
    {
        $this->validate();

        $customer = Customer::query()->notArchived()->findOrFail((int) $this->customer_id);
        $currency = Currency::from($this->currency);

        try {
            $payment = $recordPayment->handle($customer, [
                'amount' => (int) MoneyAmountRule::minorUnits($this->amount),
                'currency' => $currency,
                'paid_on' => $this->paid_on,
                'payment_method_id' => (int) $this->payment_method_id,
                'note' => trim($this->note) === '' ? null : trim($this->note),
            ], $this->show_allocations ? $this->manualAllocations() : []);
        } catch (InvalidPaymentAllocation $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $allocated = (int) $payment->allocations->sum('amount');

        $this->dispatch(self::PAYMENTS_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: $this->resultMessage($allocated, $payment->amount - $allocated, $currency));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('archived_at')],
            'amount' => ['required', new MoneyAmountRule],
            'currency' => ['required', Rule::enum(Currency::class)],
            'paid_on' => ['required', 'date'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->whereNull('archived_at')],
            'note' => ['nullable', 'string'],
            'allocations.*' => ['nullable', new MoneyAmountRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'customer_id' => __('payments.customer'),
            'amount' => __('payments.amount'),
            'currency' => __('money.currency'),
            'paid_on' => __('payments.paid_on'),
            'payment_method_id' => __('payments.payment_method'),
            'note' => __('payments.note'),
        ];
    }

    public function render(): View
    {
        $customer = $this->customer();
        $currency = Currency::tryFrom($this->currency) ?? Currency::Try;

        return view('livewire.windows.record-payment', [
            'customers' => $this->fixed ? [] : Customer::query()->notArchived()->orderBy('short_name')->pluck('short_name', 'id')->all(),
            'methods' => PaymentMethod::query()->notArchived()->orderBy('sort_order')->orderBy('id')->pluck('name', 'id')->all(),
            'currencies' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
            'customer' => $customer,
            'currencyLabel' => $currency->getLabel(),
            'charges' => $this->show_allocations && $customer !== null ? $this->openCharges($customer, $currency) : collect(),
            'summary' => $this->show_allocations ? $this->previewSummary() : '',
        ]);
    }

    private function customer(): ?Customer
    {
        return $this->customer_id === '' ? null : Customer::query()->find((int) $this->customer_id);
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
     * @return array<int, int> Amounts in minor units keyed by charge id.
     */
    private function manualAllocations(): array
    {
        $allocations = [];

        foreach ($this->allocations as $chargeId => $state) {
            $amount = MoneyAmountRule::minorUnits($state);

            if ($amount !== null && $amount > 0) {
                $allocations[(int) $chargeId] = $amount;
            }
        }

        return $allocations;
    }

    /**
     * Fill the allocation rows with the automatic split of the current amount.
     */
    private function refreshAllocations(): void
    {
        if (! $this->show_allocations) {
            return;
        }

        $customer = $this->customer();

        if ($customer === null) {
            $this->allocations = [];

            return;
        }

        $currency = Currency::tryFrom($this->currency) ?? Currency::Try;
        $amount = MoneyAmountRule::minorUnits($this->amount);

        try {
            $suggested = $amount === null || $amount <= 0
                ? []
                : app(SuggestPaymentAllocations::class)->handle($customer, $currency, $amount);
        } catch (InvalidPaymentAllocation) {
            $suggested = [];
        }

        $state = [];

        foreach ($this->openCharges($customer, $currency) as $charge) {
            $state[$charge->id] = isset($suggested[$charge->id]) ? Money::formatAmount($suggested[$charge->id]) : '';
        }

        $this->allocations = $state;
    }

    /**
     * The result the payment would have with the rows as they are now, or why it would be refused.
     */
    private function previewSummary(): string
    {
        $customer = $this->customer();
        $amount = MoneyAmountRule::minorUnits($this->amount);

        if ($customer === null || $amount === null || $amount <= 0) {
            return '';
        }

        $currency = Currency::tryFrom($this->currency) ?? Currency::Try;

        try {
            $allocations = app(SuggestPaymentAllocations::class)->handle($customer, $currency, $amount, $this->manualAllocations());
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
