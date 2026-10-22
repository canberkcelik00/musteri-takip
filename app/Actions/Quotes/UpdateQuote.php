<?php

namespace App\Actions\Quotes;

use App\Enums\Currency;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class UpdateQuote
{
    public function __construct(
        private NormalizeQuoteRecipient $normalizeRecipient,
        private BuildQuoteItem $buildItem,
    ) {}

    /**
     * Change a draft quote's recipient, notes and items in one transaction. An item with an `id`
     * of this quote keeps its copied name and only gets the new price, currency and free flag;
     * an item without one is added from the catalog (name copied); items left out are removed.
     * The list order becomes the PDF order. A sent, accepted or rejected quote is locked.
     *
     * @param  array{recipient_name?: ?string, recipient_company?: ?string, recipient_phone?: ?string, recipient_email?: ?string, notes?: ?string}  $attributes
     * @param  list<array{id?: int, catalog_service_id?: ?int, package_id?: ?int, price?: int, currency?: Currency, is_free?: bool}>  $items
     *
     * @throws InvalidQuote
     */
    public function handle(Quote $quote, ?Customer $customer, array $attributes, array $items): Quote
    {
        if ($items === []) {
            throw InvalidQuote::itemRequired();
        }

        $recipient = $this->normalizeRecipient->handle($customer, $attributes);

        return DB::transaction(function () use ($quote, $recipient, $attributes, $items): Quote {
            $locked = Quote::query()->lockForUpdate()->findOrFail($quote->getKey());

            if ($locked->status !== QuoteStatus::Draft) {
                throw InvalidQuote::notDraft();
            }

            $existing = $locked->items()->get()->keyBy('id');
            $kept = [];

            foreach ($items as $position => $item) {
                if (isset($item['id'])) {
                    $current = $existing->get($item['id']) ?? throw InvalidQuote::invalidItem();
                    $price = $item['price'] ?? $current->price;
                    $isFree = $item['is_free'] ?? $current->is_free;

                    $this->buildItem->assertPrice($price, $isFree);

                    $current->update([
                        'price' => $price,
                        'currency' => $item['currency'] ?? $current->currency,
                        'is_free' => $isFree,
                        'sort_order' => $position,
                    ]);
                    $kept[] = $current->id;

                    continue;
                }

                $created = $locked->items()->create([
                    ...$this->buildItem->handle($this->quotedBy($item), $item),
                    'sort_order' => $position,
                ]);
                $kept[] = $created->id;
            }

            $locked->items()->whereNotIn('id', $kept)->delete();
            $locked->update([...$recipient, 'notes' => $attributes['notes'] ?? null]);

            return $locked->load('items');
        });
    }

    /**
     * @param  array{catalog_service_id?: ?int, package_id?: ?int}  $item
     *
     * @throws InvalidQuote
     */
    private function quotedBy(array $item): CatalogService|Package
    {
        $catalogServiceId = $item['catalog_service_id'] ?? null;
        $packageId = $item['package_id'] ?? null;

        if (($catalogServiceId === null) === ($packageId === null)) {
            throw InvalidQuote::invalidItem();
        }

        return $catalogServiceId !== null
            ? CatalogService::findOrFail($catalogServiceId)
            : Package::findOrFail($packageId);
    }
}
