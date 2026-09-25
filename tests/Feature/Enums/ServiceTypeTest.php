<?php

use App\Enums\ServiceType;

it('has exactly the service types in the data model', function () {
    expect(array_column(ServiceType::cases(), 'value'))
        ->toBe(['hosting', 'domain', 'maintenance', 'ssl', 'email', 'license', 'other']);
});

it('matches the PRD table for each service type', function (
    ServiceType $type,
    string $label,
    array $catalogDetails,
    array $customerDetails,
) {
    expect($type->getLabel())->toBe($label)
        ->and($type->catalogDetailKeys())->toBe(array_keys($catalogDetails))
        ->and($type->customerDetailKeys())->toBe(array_keys($customerDetails))
        ->and(array_map($type->catalogDetailLabel(...), $type->catalogDetailKeys()))->toBe(array_values($catalogDetails))
        ->and(array_map($type->customerDetailLabel(...), $type->customerDetailKeys()))->toBe(array_values($customerDetails));
})->with([
    'hosting' => [
        ServiceType::Hosting,
        'Hosting',
        [
            'provider' => 'Sağlayıcı',
            'disk_space' => 'Disk alanı',
            'monthly_traffic' => 'Aylık trafik',
            'email_accounts' => 'E-posta hesabı sayısı',
            'databases' => 'Veritabanı sayısı',
            'sites' => 'Alan adı/site sayısı',
        ],
        [
            'domain' => 'Bağlı alan adı',
            'server' => 'Sunucu',
            'cpanel_username' => 'cPanel kullanıcı adı',
        ],
    ],
    'domain' => [
        ServiceType::Domain,
        'Domain',
        ['registrar' => 'Alan adı sağlayıcı', 'extension' => 'Uzantı'],
        ['domain' => 'Alan adı'],
    ],
    'maintenance' => [
        ServiceType::Maintenance,
        'Bakım',
        ['scope' => 'Kapsam açıklaması'],
        ['domain' => 'Bağlı alan adı / site'],
    ],
    'ssl' => [
        ServiceType::Ssl,
        'SSL',
        ['provider' => 'Sağlayıcı', 'certificate_type' => 'Sertifika tipi'],
        ['domain' => 'Bağlı alan adı'],
    ],
    'email' => [
        ServiceType::Email,
        'Kurumsal e-posta',
        ['provider' => 'Sağlayıcı', 'quota' => 'Kota'],
        ['domain' => 'Bağlı alan adı', 'account_count' => 'Hesap sayısı'],
    ],
    'license' => [
        ServiceType::License,
        'Lisans',
        ['product_name' => 'Ürün adı', 'provider' => 'Sağlayıcı'],
        ['quantity' => 'Adet'],
    ],
    'other' => [
        ServiceType::Other,
        'Diğer',
        ['description' => 'Açıklama'],
        ['description' => 'Açıklama'],
    ],
]);

it('keeps the license key out of the license details keys', function () {
    expect(ServiceType::License->customerDetailKeys())->not->toContain('license_key');
});

it('keeps only the customer details keys of the type', function () {
    expect(ServiceType::Email->onlyCustomerDetails(['domain' => 'logi.com.tr', 'account_count' => '5', 'server' => 'srv1']))
        ->toBe(['domain' => 'logi.com.tr', 'account_count' => '5']);
});

it('drops blank catalog and customer details but keeps "0"', function () {
    expect(ServiceType::Hosting->onlyCatalogDetails(['provider' => 'Natro', 'disk_space' => '', 'monthly_traffic' => null, 'sites' => '  ', 'databases' => '0']))
        ->toBe(['provider' => 'Natro', 'databases' => '0'])
        ->and(ServiceType::Hosting->onlyCustomerDetails(['domain' => 'logi.com.tr', 'server' => '', 'cpanel_username' => null]))
        ->toBe(['domain' => 'logi.com.tr'])
        ->and(ServiceType::Domain->onlyCatalogDetails(['registrar' => '', 'extension' => null]))
        ->toBe([]);
});
