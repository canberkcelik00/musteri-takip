<?php

namespace App\Actions\CustomerServices;

use App\Actions\Charges\CreateCharge;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignCustomerService
{
    public function __construct(private CreateCharge $createCharge) {}

    /**
     * Assign a catalog service or package to the customer. Name, price and currency are
     * copied from the catalog unless given; one item is created per sold catalog service,
     * with the customer's details keyed by catalog service id. Unless the service is free
     * or the first charge is skipped (migration from Excel), the first period's charge is
     * created, which also applies the customer's credit balance. A start date whose period
     * has already ended is rejected, so no past period charges are ever created. An archived
     * customer must be unarchived first.
     *
     * @param  array{start_date: CarbonInterface|string, price?: int, currency?: Currency, is_free?: bool, create_first_charge?: bool, notes?: ?string, items?: array<int, array{details?: array<string, mixed>|null, license_key?: string|null}>}  $attributes
     */
    public function handle(Customer $customer, CatalogService|Package $sold, array $attributes): CustomerService
    {
        if ($customer->isArchived()) {
            throw InvalidCustomerService::customerArchived();
        }

        $price = $attributes['price'] ?? $sold->price;
        $isFree = $attributes['is_free'] ?? false;

        if ($price < 0 || ($price === 0 && ! $isFree)) {
            throw InvalidCustomerService::priceNotPositive();
        }

        $periodMonths = 12;
        $startDate = CarbonImmutable::parse($attributes['start_date']);
        $endDate = $startDate->addMonthsNoOverflow($periodMonths);

        if ($endDate->toDateString() <= Carbon::today(config('app.timezone'))->toDateString()) {
            throw InvalidCustomerService::periodAlreadyEnded();
        }

        $catalogServices = $sold instanceof Package
            ? $sold->catalogServices()->orderBy('catalog_services.id')->get()
            : collect([$sold]);

        $itemDetails = $attributes['items'] ?? [];

        if (array_diff(array_keys($itemDetails), $catalogServices->pluck('id')->all()) !== []) {
            throw InvalidCustomerService::itemNotInSoldService();
        }

        return DB::transaction(function () use ($customer, $sold, $attributes, $price, $isFree, $periodMonths, $startDate, $endDate, $catalogServices, $itemDetails): CustomerService {
            $customerService = $customer->customerServices()->create([
                'catalog_service_id' => $sold instanceof CatalogService ? $sold->id : null,
                'package_id' => $sold instanceof Package ? $sold->id : null,
                'name' => $sold->name,
                'price' => $price,
                'currency' => $attributes['currency'] ?? $sold->currency,
                'period_months' => $periodMonths,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'is_free' => $isFree,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->createItems($customerService, $catalogServices, $itemDetails);

            if (! $isFree && ($attributes['create_first_charge'] ?? true)) {
                $this->createFirstCharge($customer, $customerService);
            }

            return $customerService;
        });
    }

    /**
     * @param  Collection<int, CatalogService>  $catalogServices
     * @param  array<int, array{details?: array<string, mixed>|null, license_key?: string|null}>  $itemDetails
     */
    private function createItems(CustomerService $customerService, Collection $catalogServices, array $itemDetails): void
    {
        foreach ($catalogServices as $catalogService) {
            $given = $itemDetails[$catalogService->id] ?? [];

            $customerService->items()->create([
                'catalog_service_id' => $catalogService->id,
                'type' => $catalogService->type,
                'name' => $catalogService->name,
                'details' => $given['details'] ?? null,
                'license_key' => $catalogService->type === ServiceType::License ? ($given['license_key'] ?? null) : null,
            ]);
        }
    }

    private function createFirstCharge(Customer $customer, CustomerService $customerService): void
    {
        $this->createCharge->handle($customer, [
            'customer_service_id' => $customerService->id,
            'description' => $customerService->name.' '.$customerService->start_date->format('Y').'–'.$customerService->end_date->format('Y'),
            'amount' => $customerService->price,
            'currency' => $customerService->currency,
            'due_date' => $customerService->start_date->toDateString(),
            'period_start' => $customerService->start_date->toDateString(),
            'period_end' => $customerService->end_date->toDateString(),
        ]);
    }
}
