<?php

namespace App\Actions\CustomerServices;

use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Models\CustomerService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateCustomerService
{
    /**
     * Update the price, currency, dates and notes of the customer service and the details
     * of its items (keyed by item id). Charges that already exist are never touched: a new
     * price or date only shows in charges created later. Once the upcoming period's charge
     * exists (cancelled ones included), the end date cannot change, as the renewal would
     * otherwise create a second charge; an active service's end date must stay after today.
     * A cancelled service only takes notes and item details. The license key is stored for
     * license items only; the item's type and name never change.
     *
     * @param  array{price?: int, currency?: Currency, start_date?: CarbonInterface|string, end_date?: CarbonInterface|string, notes?: ?string, items?: array<int, array{details?: array<string, mixed>|null, license_key?: string|null}>}  $attributes
     *
     * @throws InvalidCustomerService
     */
    public function handle(CustomerService $customerService, array $attributes): CustomerService
    {
        return DB::transaction(function () use ($customerService, $attributes): CustomerService {
            $customerService = CustomerService::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            $changes = $this->serviceChanges($customerService, $attributes);
            $itemDetails = $attributes['items'] ?? [];

            if (array_diff(array_keys($itemDetails), $customerService->items->modelKeys()) !== []) {
                throw InvalidCustomerService::itemNotInSoldService();
            }

            $customerService->update($changes);

            foreach ($customerService->items as $item) {
                if (! array_key_exists($item->id, $itemDetails)) {
                    continue;
                }

                $given = $itemDetails[$item->id];

                $values = ['details' => $given['details'] ?? null];

                if ($item->type === ServiceType::License) {
                    $values['license_key'] = $given['license_key'] ?? null;
                }

                $item->update($values);
            }

            return $customerService;
        });
    }

    /**
     * Validate the given attributes and return the columns of the service that change.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws InvalidCustomerService
     */
    private function serviceChanges(CustomerService $customerService, array $attributes): array
    {
        $changes = [];

        if (array_key_exists('notes', $attributes)) {
            $changes['notes'] = $attributes['notes'];
        }

        $price = $attributes['price'] ?? $customerService->price;
        $currency = $attributes['currency'] ?? $customerService->currency;
        $startDate = CarbonImmutable::parse($attributes['start_date'] ?? $customerService->start_date)->toDateString();
        $endDate = CarbonImmutable::parse($attributes['end_date'] ?? $customerService->end_date)->toDateString();

        $priceChanged = $price !== $customerService->price || $currency !== $customerService->currency;
        $startChanged = $startDate !== $customerService->start_date->toDateString();
        $endChanged = $endDate !== $customerService->end_date->toDateString();

        if (! $priceChanged && ! $startChanged && ! $endChanged) {
            return $changes;
        }

        if ($customerService->status === CustomerServiceStatus::Cancelled) {
            throw InvalidCustomerService::cancelledServiceLocked();
        }

        if ($price < 0 || ($price === 0 && ! $customerService->is_free)) {
            throw InvalidCustomerService::priceNotPositive();
        }

        if ($endDate <= $startDate) {
            throw InvalidCustomerService::endDateNotAfterStartDate();
        }

        if ($endChanged) {
            if ($endDate <= Carbon::today(config('app.timezone'))->toDateString()) {
                throw InvalidCustomerService::endDateNotInFuture();
            }

            $upcomingChargeExists = $customerService->charges()
                ->whereDate('period_start', '>=', $customerService->end_date->toDateString())
                ->exists();

            if ($upcomingChargeExists) {
                throw InvalidCustomerService::upcomingChargeExists();
            }
        }

        return [
            ...$changes,
            'price' => $price,
            'currency' => $currency,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
