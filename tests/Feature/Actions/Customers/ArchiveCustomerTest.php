<?php

use App\Actions\Customers\ArchiveCustomer;
use App\Exceptions\CustomerHasActiveServices;
use App\Models\Customer;
use App\Models\CustomerService;

it('archives a customer without services', function () {
    $customer = Customer::factory()->create();

    app(ArchiveCustomer::class)->handle($customer);

    expect($customer->fresh()->isArchived())->toBeTrue();
});

it('archives a customer whose services are all cancelled', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->cancelled()->count(2)->create(['customer_id' => $customer->id]);

    app(ArchiveCustomer::class)->handle($customer);

    expect($customer->fresh()->isArchived())->toBeTrue();
});

it('does not archive a customer with an active service', function (bool $isFree) {
    $customer = Customer::factory()->create();
    CustomerService::factory()->cancelled()->create(['customer_id' => $customer->id]);
    CustomerService::factory()->create(['customer_id' => $customer->id, 'is_free' => $isFree]);

    expect(fn () => app(ArchiveCustomer::class)->handle($customer))
        ->toThrow(CustomerHasActiveServices::class, 'Önce müşterinin hizmetlerini iptal edin.');

    expect($customer->fresh()->isArchived())->toBeFalse();
})->with([
    'paid service' => false,
    'free service' => true,
]);
