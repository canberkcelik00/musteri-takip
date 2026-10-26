<?php

namespace App\Livewire\Windows;

use App\Actions\Quotes\CreateQuote as CreateQuoteAction;
use App\Exceptions\InvalidQuote;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Customer;
use App\Support\Ui\Links;
use App\Support\Ui\QuotableItems;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Starts a draft quote with the catalog service or package it was opened for — from a catalog row's "Teklif
 * oluştur" — or, from the quote list's "Yeni teklif", with one picked in the window. The recipient is an existing
 * customer or a person written by hand. It only calls CreateQuote; the draft is then opened in the quote list, where
 * "Düzenle" adds items and "Gönder" sends it (docs/UI.md §5).
 */
class CreateQuote extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    /** The `catalog_service:ID` or `package:ID` the window was opened for; empty when the user picks it. */
    #[Locked]
    public string $quoted = '';

    public string $sold = '';

    public string $customer_id = '';

    public string $recipient_name = '';

    public string $recipient_company = '';

    public string $recipient_phone = '';

    public string $recipient_email = '';

    public string $notes = '';

    public function mount(?string $sold = null): void
    {
        $this->quoted = $sold !== null && QuotableItems::resolve($sold) !== null ? $sold : '';
    }

    public function save(CreateQuoteAction $createQuote): void
    {
        $this->validate($this->rules(), attributes: $this->validationAttributes());

        $quoted = QuotableItems::resolve($this->quoted !== '' ? $this->quoted : $this->sold);

        if ($quoted === null || $quoted->isArchived()) {
            $this->addError('sold', __('validation.required', ['attribute' => __('quotes.sold')]));

            return;
        }

        $customer = $this->customer_id === '' ? null : Customer::query()->find((int) $this->customer_id);

        try {
            $quote = $createQuote->handle($quoted, $customer, [
                'recipient_name' => $this->nullIfBlank($this->recipient_name),
                'recipient_company' => $this->nullIfBlank($this->recipient_company),
                'recipient_phone' => $this->nullIfBlank($this->recipient_phone),
                'recipient_email' => $this->nullIfBlank($this->recipient_email),
                'notes' => $this->nullIfBlank($this->notes),
            ]);
        } catch (InvalidQuote $exception) {
            $this->reportDomainError($exception, ['recipient_name', 'recipient_email']);

            return;
        }

        $this->dispatch('quotes-changed');
        $this->dispatch('notify', message: __('quotes.created', ['number' => $quote->number]));
        $this->redirect(Links::quotes());
    }

    public function render(): View
    {
        return view('livewire.windows.create-quote', [
            'catalog' => QuotableItems::options(),
            'customers' => Customer::query()->notArchived()->orderBy('short_name')->pluck('short_name', 'id')->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $noCustomer = fn (): bool => $this->customer_id === '';

        return [
            'sold' => [$this->quoted === '' ? 'required' : 'nullable', 'string'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('archived_at')],
            'recipient_name' => [Rule::requiredIf($noCustomer), 'nullable', 'string', 'max:255'],
            'recipient_company' => ['nullable', 'string', 'max:255'],
            'recipient_email' => [Rule::requiredIf($noCustomer), 'nullable', 'email', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'sold' => __('quotes.sold'),
            'customer_id' => __('quotes.customer'),
            'recipient_name' => __('quotes.recipient_name'),
            'recipient_company' => __('quotes.recipient_company'),
            'recipient_email' => __('quotes.recipient_email'),
            'recipient_phone' => __('quotes.recipient_phone'),
            'notes' => __('quotes.notes'),
        ];
    }

    private function nullIfBlank(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
