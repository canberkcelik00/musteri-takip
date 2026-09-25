<?php

use App\Enums\ServiceType;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('stores the license key encrypted in the database', function () {
    $item = CustomerServiceItem::factory()->ofType(ServiceType::License)->create([
        'license_key' => 'ABCDE-12345-FGHIJ-67890',
    ]);

    $stored = DB::table('customer_service_items')->where('id', $item->id)->value('license_key');

    expect($item->fresh()->license_key)->toBe('ABCDE-12345-FGHIJ-67890')
        ->and($stored)->not->toContain('ABCDE-12345-FGHIJ-67890')
        ->and(Crypt::decryptString($stored))->toBe('ABCDE-12345-FGHIJ-67890');
});

it('has a license key factory state', function () {
    $item = CustomerServiceItem::factory()->withLicenseKey()->create();

    expect($item->type)->toBe(ServiceType::License)
        ->and($item->fresh()->license_key)->not->toBeEmpty();
});

it('drops details keys that do not belong to the type', function () {
    $item = CustomerServiceItem::factory()->create([
        'type' => ServiceType::Hosting,
        'details' => ['domain' => 'logi.com.tr', 'server' => 'srv1', 'cpanel_username' => 'logi', 'quota' => '10 GB', 'foo' => 'bar'],
    ]);

    expect($item->fresh()->details)->toEqual(['domain' => 'logi.com.tr', 'server' => 'srv1', 'cpanel_username' => 'logi']);
});

it('does not save blank details', function () {
    $item = CustomerServiceItem::factory()->create([
        'type' => ServiceType::Hosting,
        'details' => ['domain' => 'logi.com.tr', 'server' => '', 'cpanel_username' => null],
    ]);

    expect($item->fresh()->details)->toBe(['domain' => 'logi.com.tr']);
});

it('drops the details of the previous type when the type changes', function () {
    $item = CustomerServiceItem::factory()->create([
        'type' => ServiceType::Hosting,
        'details' => ['domain' => 'logi.com.tr', 'server' => 'srv1'],
    ]);

    $item->update([
        'type' => ServiceType::Email,
        'details' => [...$item->details, 'account_count' => '5'],
    ]);

    expect($item->fresh()->details)->toEqual(['domain' => 'logi.com.tr', 'account_count' => '5']);
});

it('belongs to a customer service', function () {
    $customerService = CustomerService::factory()->create();

    $item = CustomerServiceItem::factory()->for($customerService)->create();

    expect($item->customerService->is($customerService))->toBeTrue();
});
