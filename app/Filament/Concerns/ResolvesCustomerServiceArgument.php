<?php

namespace App\Filament\Concerns;

use App\Models\CustomerService;
use Illuminate\Support\Collection;

/**
 * For actions on rows that are not table rows ("Bugün" free-decision rows): the record comes
 * from a `customerService` argument. A page that already loaded its services passes them with
 * {@see self::knownServices()}, so rendering a row does not query again for every closure that
 * asks for the record; without them (the action being run) the service is looked up.
 */
trait ResolvesCustomerServiceArgument
{
    /** @var Collection<int, CustomerService>|null */
    protected ?Collection $knownServices = null;

    /**
     * @param  Collection<int, CustomerService>|null  $services  Keyed by id.
     */
    public function knownServices(?Collection $services): static
    {
        $this->knownServices = $services;

        return $this;
    }

    protected function hasCustomerServiceArgument(): bool
    {
        return ($this->getArguments()['customerService'] ?? null) !== null;
    }

    protected function customerServiceFromArguments(): ?CustomerService
    {
        $customerServiceId = $this->getArguments()['customerService'] ?? null;

        if ($customerServiceId === null) {
            return null;
        }

        return $this->knownServices?->get($customerServiceId) ?? CustomerService::query()->find($customerServiceId);
    }
}
