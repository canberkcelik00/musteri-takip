<?php

namespace App\Livewire\Windows;

use App\Actions\Quotes\AcceptQuote as AcceptQuoteAction;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Quote;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Accepts a sent, still valid quote through AcceptQuote. The window asks the start date (today) and, for a
 * hand-written recipient, the short name of the customer that is created (the company, else the person's name); a
 * refusal appears under the field it is about. Everything is created together or not at all.
 */
class AcceptQuote extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    #[Locked]
    public int $quoteId = 0;

    public string $start_date = '';

    public string $short_name = '';

    public function mount(int $quote): void
    {
        $record = Quote::query()->findOrFail($quote);

        $this->quoteId = $record->id;
        $this->start_date = today()->toDateString();
        $this->short_name = $record->customer_id === null ? (string) ($record->recipient_company ?: $record->recipient_name) : '';
    }

    public function save(AcceptQuoteAction $acceptQuote): void
    {
        $quote = Quote::query()->findOrFail($this->quoteId);
        $needsName = $quote->customer_id === null;

        $this->validate([
            'start_date' => ['required', 'date'],
            'short_name' => [$needsName ? 'required' : 'nullable', 'string', 'max:255'],
        ], attributes: ['start_date' => __('quotes.start_date'), 'short_name' => __('quotes.short_name')]);

        try {
            $accepted = $acceptQuote->handle($quote, $this->start_date, $needsName ? $this->short_name : null);
        } catch (InvalidQuote|InvalidCustomerService $exception) {
            $this->reportDomainError($exception, ['start_date', 'short_name']);

            return;
        }

        $this->dispatch('quotes-changed');
        $this->dispatch(AssignService::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('quotes.accepted', ['count' => $accepted->items->count()]));
    }

    public function render(): View
    {
        $quote = Quote::query()->findOrFail($this->quoteId);

        return view('livewire.windows.accept-quote', [
            'needsName' => $quote->customer_id === null,
            'acceptable' => $quote->status === QuoteStatus::Sent && ! $quote->isExpired(),
        ]);
    }
}
