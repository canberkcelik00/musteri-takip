<?php

namespace App\Actions\Quotes;

use App\Actions\Customers\CreateCustomer;
use App\Actions\CustomerServices\AssignCustomerService;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AcceptQuote
{
    public function __construct(
        private LockQuoteForDecision $lockQuote,
        private CreateCustomer $createCustomer,
        private AssignCustomerService $assignCustomerService,
    ) {}

    /**
     * Accept a sent, still valid quote: every item becomes its own customer service with the
     * quoted name, price, currency and free flag, all starting on the same date (today unless
     * given), and each paid one gets its first period's charge (the customer's credit balance is
     * applied as usual). A quote to a hand-written recipient first creates the customer: the
     * short name is the one given, else the company, else the person's name, and the quote's
     * person becomes the primary contact. All in one transaction, so a failing item leaves
     * nothing behind.
     *
     * @param  string|null  $shortName  Short name of the new customer; only used for a hand-written recipient.
     *
     * @throws InvalidQuote
     * @throws InvalidCustomerService When a service cannot be assigned, e.g. its period would already have ended.
     */
    public function handle(Quote $quote, CarbonInterface|string|null $startDate = null, ?string $shortName = null): Quote
    {
        $startDate ??= Carbon::today(config('app.timezone'));

        return DB::transaction(function () use ($quote, $startDate, $shortName): Quote {
            $locked = $this->lockQuote->handle($quote);
            $locked->load(['customer', 'items.catalogService', 'items.package']);

            $customer = $locked->customer ?? $this->createCustomerFor($locked, $shortName);

            if ($customer->isArchived()) {
                throw InvalidQuote::customerArchived();
            }

            foreach ($locked->items as $item) {
                $this->assignCustomerService->handle($customer, $this->soldBy($item), [
                    'start_date' => $startDate,
                    'name' => $item->name,
                    'quote_item_id' => $item->id,
                    'price' => $item->price,
                    'currency' => $item->currency,
                    'is_free' => $item->is_free,
                ]);
            }

            $locked->update([
                'customer_id' => $customer->id,
                'status' => QuoteStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * The customer of a hand-written recipient, with the recipient as the primary contact.
     *
     * @throws InvalidQuote When the short name belongs to another customer.
     */
    private function createCustomerFor(Quote $quote, ?string $shortName): Customer
    {
        $name = $this->firstFilled([$shortName, $quote->recipient_company, $quote->recipient_name]);

        if ($name === null) {
            throw InvalidQuote::recipientRequired();
        }

        if (Customer::query()->where('short_name', $name)->exists()) {
            throw InvalidQuote::shortNameTaken();
        }

        return $this->createCustomer->handle(
            ['short_name' => $name],
            [
                'name' => (string) $quote->recipient_name,
                'phone' => $quote->recipient_phone,
                'email' => $quote->recipient_email,
            ],
        );
    }

    /**
     * @param  list<?string>  $candidates
     */
    private function firstFilled(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function soldBy(QuoteItem $item): CatalogService|Package
    {
        return $item->catalogService ?? $item->package ?? throw InvalidQuote::invalidItem();
    }
}
