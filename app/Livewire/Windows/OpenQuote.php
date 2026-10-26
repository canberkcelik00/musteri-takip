<?php

namespace App\Livewire\Windows;

use App\Actions\Quotes\UpdateQuote;
use App\Enums\Currency;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use App\Support\Ui\QuotableItems;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A quote's window (docs/UI.md §5 Teklifler). A draft opens as an editable form saved through UpdateQuote; a quote
 * that was sent opens with the same fields locked and its rarer actions (PDF indir, Tekrar gönder, Kabul et, Reddet)
 * below, a draft with PDF indir, Gönder and, apart, Sil.
 */
class OpenQuote extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    #[Locked]
    public int $quoteId = 0;

    public string $customer_id = '';

    public string $recipient_name = '';

    public string $recipient_company = '';

    public string $recipient_phone = '';

    public string $recipient_email = '';

    public string $notes = '';

    /** @var list<array{id: ?int, name: string, item: string, price: string, currency: string, is_free: bool}> */
    public array $items = [];

    public function mount(int $quote): void
    {
        $record = Quote::query()->with('items')->findOrFail($quote);

        $this->quoteId = $record->id;
        $this->customer_id = $record->customer_id === null ? '' : (string) $record->customer_id;
        $this->recipient_name = (string) $record->recipient_name;
        $this->recipient_company = (string) $record->recipient_company;
        $this->recipient_phone = (string) $record->recipient_phone;
        $this->recipient_email = (string) $record->recipient_email;
        $this->notes = (string) $record->notes;
        $this->items = $record->items->map(fn (QuoteItem $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'item' => '',
            'price' => Money::formatAmount($item->price),
            'currency' => $item->currency->value,
            'is_free' => (bool) $item->is_free,
        ])->values()->all();
    }

    public function addItem(): void
    {
        if (! $this->isDraft()) {
            return;
        }

        $this->items[] = ['id' => null, 'name' => '', 'item' => '', 'price' => '', 'currency' => 'TRY', 'is_free' => false];
    }

    public function removeItem(int $index): void
    {
        if (! $this->isDraft()) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function moveItem(int $index, int $step): void
    {
        $target = $index + $step;

        if (! $this->isDraft() || ! isset($this->items[$index], $this->items[$target])) {
            return;
        }

        [$this->items[$index], $this->items[$target]] = [$this->items[$target], $this->items[$index]];
    }

    /**
     * A catalog service or package picked for a new line takes the catalog's price and currency, which can then be
     * changed.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.item')) {
            return;
        }

        $quoted = QuotableItems::resolve($value);

        if ($quoted === null) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $this->items[$index]['price'] = Money::formatAmount($quoted->price);
        $this->items[$index]['currency'] = $quoted->currency->value;
    }

    public function save(UpdateQuote $updateQuote): void
    {
        $quote = Quote::query()->findOrFail($this->quoteId);

        if ($quote->status !== QuoteStatus::Draft) {
            return;
        }

        $this->validate($this->rules(), attributes: $this->validationAttributes());

        $customer = $this->customer_id === '' ? null : Customer::query()->find((int) $this->customer_id);

        try {
            $updateQuote->handle($quote, $customer, [
                'recipient_name' => $this->nullIfBlank($this->recipient_name),
                'recipient_company' => $this->nullIfBlank($this->recipient_company),
                'recipient_phone' => $this->nullIfBlank($this->recipient_phone),
                'recipient_email' => $this->nullIfBlank($this->recipient_email),
                'notes' => $this->nullIfBlank($this->notes),
            ], $this->itemsForAction());
        } catch (InvalidQuote $exception) {
            $this->reportDomainError($exception, ['recipient_name', 'recipient_email', 'items']);

            return;
        }

        $this->dispatch('quotes-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('quotes.saved'));
    }

    public function render(): View
    {
        $quote = Quote::query()->findOrFail($this->quoteId);
        $draft = $quote->status === QuoteStatus::Draft;

        return view('livewire.windows.open-quote', [
            'quote' => $quote,
            'draft' => $draft,
            'canSend' => in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent], true),
            'resend' => $quote->status !== QuoteStatus::Draft,
            'canDecide' => $quote->status === QuoteStatus::Sent && ! $quote->isExpired(),
            'customers' => Customer::query()->notArchived()->orderBy('short_name')->pluck('short_name', 'id')->all(),
            'catalog' => QuotableItems::options(),
            'currencies' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $noCustomer = fn (): bool => $this->customer_id === '';
        $rules = [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('archived_at')],
            'recipient_name' => [Rule::requiredIf($noCustomer), 'nullable', 'string', 'max:255'],
            'recipient_company' => ['nullable', 'string', 'max:255'],
            'recipient_email' => [Rule::requiredIf($noCustomer), 'nullable', 'email', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        foreach ($this->items as $index => $row) {
            $isNew = ($row['id'] ?? null) === null;

            $rules["items.{$index}.item"] = [Rule::requiredIf($isNew), 'nullable', 'string'];
            $rules["items.{$index}.price"] = [Rule::requiredIf(! $row['is_free']), 'nullable', new MoneyAmountRule];
            $rules["items.{$index}.currency"] = ['required', Rule::enum(Currency::class)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = [
            'customer_id' => __('quotes.customer'),
            'recipient_name' => __('quotes.recipient_name'),
            'recipient_company' => __('quotes.recipient_company'),
            'recipient_email' => __('quotes.recipient_email'),
            'recipient_phone' => __('quotes.recipient_phone'),
            'notes' => __('quotes.notes'),
        ];

        foreach (array_keys($this->items) as $index) {
            $attributes["items.{$index}.item"] = __('quotes.item');
            $attributes["items.{$index}.price"] = __('quotes.price');
            $attributes["items.{$index}.currency"] = __('money.currency');
        }

        return $attributes;
    }

    /**
     * The window's lines as UpdateQuote takes them: an existing line by `id`, a new one by the catalog service or
     * package picked.
     *
     * @return list<array{id?: int, catalog_service_id?: int, package_id?: int, price?: int, currency?: Currency, is_free?: bool}>
     */
    private function itemsForAction(): array
    {
        $lines = [];

        foreach ($this->items as $row) {
            $line = [
                'price' => MoneyAmountRule::minorUnits($row['price']),
                'currency' => Currency::from($row['currency']),
                'is_free' => (bool) $row['is_free'],
            ];

            if (($row['id'] ?? null) !== null) {
                $lines[] = ['id' => (int) $row['id'], ...$line];

                continue;
            }

            $quoted = QuotableItems::resolve($row['item']) ?? throw InvalidQuote::invalidItem();

            $lines[] = [($quoted instanceof Package ? 'package_id' : 'catalog_service_id') => $quoted->id, ...$line];
        }

        return $lines;
    }

    private function isDraft(): bool
    {
        return Quote::query()->whereKey($this->quoteId)->where('status', QuoteStatus::Draft)->exists();
    }

    private function nullIfBlank(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
