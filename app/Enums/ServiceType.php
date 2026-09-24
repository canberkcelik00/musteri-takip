<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasLabel
{
    case Hosting = 'hosting';
    case Domain = 'domain';
    case Maintenance = 'maintenance';
    case Ssl = 'ssl';
    case Email = 'email';
    case License = 'license';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('enums.service_type.'.$this->value);
    }

    /**
     * Keys stored in `catalog_services.details` for this type.
     *
     * @return list<string>
     */
    public function catalogDetailKeys(): array
    {
        return match ($this) {
            self::Hosting => ['provider', 'disk_space', 'monthly_traffic', 'email_accounts', 'databases', 'sites'],
            self::Domain => ['registrar', 'extension'],
            self::Maintenance => ['scope'],
            self::Ssl => ['provider', 'certificate_type'],
            self::Email => ['provider', 'quota'],
            self::License => ['product_name', 'provider'],
            self::Other => ['description'],
        };
    }

    /**
     * Keys stored in `customer_service_items.details` for this type.
     *
     * The license key is not a details key; it lives in the encrypted
     * `customer_service_items.license_key` column.
     *
     * @return list<string>
     */
    public function customerDetailKeys(): array
    {
        return match ($this) {
            self::Hosting => ['domain', 'server', 'cpanel_username'],
            self::Domain => ['domain'],
            self::Maintenance => ['domain'],
            self::Ssl => ['domain'],
            self::Email => ['domain', 'account_count'],
            self::License => ['quantity'],
            self::Other => ['description'],
        };
    }

    /**
     * Keep only the catalog details keys that belong to this type.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function onlyCatalogDetails(array $details): array
    {
        return array_intersect_key($details, array_flip($this->catalogDetailKeys()));
    }

    /**
     * Keep only the customer details keys that belong to this type.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function onlyCustomerDetails(array $details): array
    {
        return array_intersect_key($details, array_flip($this->customerDetailKeys()));
    }

    public function catalogDetailLabel(string $key): string
    {
        return __("enums.service_type_details.{$this->value}.catalog.{$key}");
    }

    public function customerDetailLabel(string $key): string
    {
        return __("enums.service_type_details.{$this->value}.customer.{$key}");
    }
}
