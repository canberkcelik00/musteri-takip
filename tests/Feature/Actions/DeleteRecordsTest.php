<?php

use App\Actions\CatalogServices\DeleteCatalogService;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\CustomerServices\DeleteCustomerService;
use App\Actions\Packages\DeletePackage;
use App\Actions\PaymentMethods\DeletePaymentMethod;
use App\Exceptions\LastActivePaymentMethod;
use App\Exceptions\RecordInUse;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

describe('customer', function () {
    it('deletes a customer with no service, charge or payment together with its contacts', function () {
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->primary()->create();

        app(DeleteCustomer::class)->handle($customer);

        expect(Customer::query()->find($customer->id))->toBeNull()
            ->and(Contact::query()->find($contact->id))->toBeNull();
    });

    it('does not delete a customer with a service, even a cancelled one', function (?string $state) {
        $service = $state === null ? CustomerService::factory()->create() : CustomerService::factory()->{$state}()->create();

        expect(fn () => app(DeleteCustomer::class)->handle($service->customer))
            ->toThrow(RecordInUse::class, 'Müşterinin hizmeti, borcu veya ödemesi var; silinemez, arşivlenebilir.');

        expect(Customer::query()->find($service->customer_id))->not->toBeNull();
    })->with(['active' => null, 'free' => 'free', 'cancelled' => 'cancelled']);

    it('does not delete a customer with a charge', function () {
        $charge = Charge::factory()->create();

        expect(fn () => app(DeleteCustomer::class)->handle($charge->customer))->toThrow(RecordInUse::class);

        expect(Customer::query()->find($charge->customer_id))->not->toBeNull();
    });

    it('does not delete a customer with a payment', function () {
        $payment = Payment::factory()->create();

        expect(fn () => app(DeleteCustomer::class)->handle($payment->customer))->toThrow(RecordInUse::class);

        expect(Customer::query()->find($payment->customer_id))->not->toBeNull();
    });

    it('can delete an archived customer that nothing depends on', function () {
        $customer = Customer::factory()->create();
        $customer->archive();

        app(DeleteCustomer::class)->handle($customer);

        expect(Customer::query()->find($customer->id))->toBeNull();
    });

    it('does nothing when the customer is already gone', function () {
        $customer = Customer::factory()->create();
        $stale = Customer::query()->findOrFail($customer->id);
        $customer->delete();

        app(DeleteCustomer::class)->handle($stale);

        expect(Customer::query()->count())->toBe(0);
    });
});

describe('customer service', function () {
    it('deletes a service with no charge together with its items', function () {
        $service = CustomerService::factory()->forPackage()->create();
        $itemIds = $service->items()->pluck('id');
        expect($itemIds)->not->toBeEmpty();

        app(DeleteCustomerService::class)->handle($service);

        expect(CustomerService::query()->find($service->id))->toBeNull()
            ->and(CustomerServiceItem::query()->whereIn('id', $itemIds)->count())->toBe(0);
    });

    it('does not delete a service that has a charge', function (bool $cancelled) {
        $service = CustomerService::factory()->create();
        $charge = Charge::factory()->forCustomerService($service);
        ($cancelled ? $charge->cancelled() : $charge)->create();

        expect(fn () => app(DeleteCustomerService::class)->handle($service))
            ->toThrow(RecordInUse::class, 'Hizmetin borcu var; silinemez, iptal edilebilir.');

        expect(CustomerService::query()->find($service->id))->not->toBeNull();
    })->with(['open charge' => false, 'cancelled charge' => true]);
});

describe('catalog service', function () {
    it('deletes a catalog service that nothing uses', function () {
        $catalogService = CatalogService::factory()->create();

        app(DeleteCatalogService::class)->handle($catalogService);

        expect(CatalogService::query()->find($catalogService->id))->toBeNull();
    });

    it('does not delete a catalog service that is in a package', function () {
        $catalogService = CatalogService::factory()->create();
        Package::factory()->create()->catalogServices()->attach($catalogService);

        expect(fn () => app(DeleteCatalogService::class)->handle($catalogService))
            ->toThrow(RecordInUse::class, 'Bu hizmet bir pakette veya müşteri hizmetinde kullanılıyor; silinemez, arşivlenebilir.');

        expect(CatalogService::query()->find($catalogService->id))->not->toBeNull();
    });

    it('does not delete a catalog service sold to a customer', function () {
        $catalogService = CatalogService::factory()->create();
        CustomerService::factory()->forCatalogService($catalogService)->create();

        expect(fn () => app(DeleteCatalogService::class)->handle($catalogService))->toThrow(RecordInUse::class);

        expect(CatalogService::query()->find($catalogService->id))->not->toBeNull();
    });

    it('does not delete a catalog service that only remains as an item of a sold package', function () {
        $package = Package::factory()->withCatalogServices()->create();
        CustomerService::factory()->forPackage($package)->create();
        $inner = $package->catalogServices()->firstOrFail();
        $package->catalogServices()->detach($inner);

        expect(fn () => app(DeleteCatalogService::class)->handle($inner))->toThrow(RecordInUse::class);
    });
});

describe('package', function () {
    it('deletes a package that nothing uses together with its content rows', function () {
        $package = Package::factory()->withCatalogServices()->create();
        $catalogServiceIds = $package->catalogServices()->pluck('catalog_services.id');

        app(DeletePackage::class)->handle($package);

        expect(Package::query()->find($package->id))->toBeNull()
            ->and(CatalogService::query()->whereIn('id', $catalogServiceIds)->count())->toBe(count($catalogServiceIds))
            ->and($package->catalogServices()->count())->toBe(0);
    });

    it('does not delete a package sold to a customer', function () {
        $package = Package::factory()->withCatalogServices()->create();
        CustomerService::factory()->forPackage($package)->create();

        expect(fn () => app(DeletePackage::class)->handle($package))
            ->toThrow(RecordInUse::class, 'Bu paket bir müşteri hizmetinde kullanılıyor; silinemez, arşivlenebilir.');

        expect(Package::query()->find($package->id))->not->toBeNull();
    });
});

describe('payment method', function () {
    it('deletes a method no payment uses', function () {
        $method = PaymentMethod::factory()->create();

        app(DeletePaymentMethod::class)->handle($method);

        expect(PaymentMethod::query()->find($method->id))->toBeNull();
    });

    it('does not delete a method a payment uses', function () {
        $payment = Payment::factory()->create();

        expect(fn () => app(DeletePaymentMethod::class)->handle($payment->paymentMethod))
            ->toThrow(RecordInUse::class, 'Bu ödeme yöntemi ödemelerde kullanılıyor; silinemez, arşivlenebilir.');

        expect(PaymentMethod::query()->find($payment->payment_method_id))->not->toBeNull();
    });

    it('does not delete the last active method', function () {
        PaymentMethod::query()->where('name', '!=', 'Nakit')->get()->each->archive();
        $cash = PaymentMethod::query()->where('name', 'Nakit')->firstOrFail();

        expect(fn () => app(DeletePaymentMethod::class)->handle($cash))->toThrow(LastActivePaymentMethod::class);

        expect(PaymentMethod::query()->find($cash->id))->not->toBeNull();
    });

    it('deletes an archived method even when it is the only archived one and one stays active', function () {
        $archived = PaymentMethod::factory()->create();
        $archived->archive();

        app(DeletePaymentMethod::class)->handle($archived);

        expect(PaymentMethod::query()->find($archived->id))->toBeNull()
            ->and(PaymentMethod::query()->notArchived()->count())->toBe(3);
    });
});

it('reports usage without querying per row when the list query loads it', function () {
    $used = Customer::factory()->create();
    Charge::factory()->for($used)->create();
    $unused = Customer::factory()->create();

    $customers = Customer::query()->withInUse()->orderBy('id')->get();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($customers->pluck('id')->all())->toBe([$used->id, $unused->id])
        ->and($customers[0]->isInUse())->toBeTrue()
        ->and($customers[1]->isInUse())->toBeFalse()
        ->and($queries)->toBe(0);
});
