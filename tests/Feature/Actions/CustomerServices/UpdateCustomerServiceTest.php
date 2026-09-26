<?php

use App\Actions\CustomerServices\UpdateCustomerService;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;

/**
 * An active 4.000 TL service for the period 01.03.2026 – 01.03.2027 with today set to 15.06.2026.
 */
function serviceToUpdate(array $attributes = []): CustomerService
{
    test()->travelTo('2026-06-15 10:00:00');

    return CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

function updateService(CustomerService $customerService, array $attributes): CustomerService
{
    return app(UpdateCustomerService::class)->handle($customerService, $attributes);
}

it('updates price, currency, dates and notes', function () {
    $customerService = serviceToUpdate();

    updateService($customerService, [
        'price' => 450000,
        'currency' => Currency::Usd,
        'start_date' => '2026-03-05',
        'end_date' => '2027-03-05',
        'notes' => 'Fiyat güncellendi.',
    ]);

    $customerService->refresh();

    expect($customerService->price)->toBe(450000)
        ->and($customerService->currency)->toBe(Currency::Usd)
        ->and($customerService->start_date->toDateString())->toBe('2026-03-05')
        ->and($customerService->end_date->toDateString())->toBe('2027-03-05')
        ->and($customerService->notes)->toBe('Fiyat güncellendi.');
});

it('leaves existing charges untouched', function () {
    $customerService = serviceToUpdate();
    $charge = Charge::factory()->forCustomerService($customerService)->create();

    updateService($customerService, [
        'price' => 450000,
        'currency' => Currency::Usd,
        'start_date' => '2026-03-05',
        'end_date' => '2027-03-05',
    ]);

    $charge->refresh();

    expect($charge->amount)->toBe(400000)
        ->and($charge->currency)->toBe(Currency::Try)
        ->and($charge->due_date->toDateString())->toBe('2026-03-01')
        ->and($charge->period_start->toDateString())->toBe('2026-03-01');
});

it('rejects a price of zero or less for a paid service and accepts zero for a free one', function () {
    $paid = serviceToUpdate();
    $free = CustomerService::factory()->free()->create(['start_date' => '2026-03-01', 'end_date' => '2027-03-01']);

    expect(fn () => updateService($paid, ['price' => 0]))->toThrow(InvalidCustomerService::class, 'Fiyat sıfırdan büyük olmalıdır')
        ->and(fn () => updateService($paid, ['price' => -100]))->toThrow(InvalidCustomerService::class)
        ->and($paid->fresh()->price)->toBe(400000);

    updateService($free, ['price' => 0]);

    expect($free->fresh()->price)->toBe(0);
});

it('rejects an end date that is not after the start date', function (string $startDate, string $endDate) {
    $customerService = serviceToUpdate();

    expect(fn () => updateService($customerService, ['start_date' => $startDate, 'end_date' => $endDate]))
        ->toThrow(InvalidCustomerService::class, 'Bitiş tarihi başlangıç tarihinden sonra olmalıdır.');

    expect($customerService->fresh()->start_date->toDateString())->toBe('2026-03-01');
})->with([
    'before' => ['2026-09-01', '2026-08-01'],
    'same day' => ['2026-09-01', '2026-09-01'],
]);

it('rejects an end date of an active service that is today or in the past', function (string $endDate) {
    $customerService = serviceToUpdate();

    expect(fn () => updateService($customerService, ['start_date' => '2025-01-01', 'end_date' => $endDate]))
        ->toThrow(InvalidCustomerService::class, 'Aktif hizmetin bitiş tarihi bugünden sonra olmalıdır.');

    expect($customerService->fresh()->end_date->toDateString())->toBe('2027-03-01');
})->with(['today' => '2026-06-15', 'past' => '2026-05-01']);

it('accepts the day after today as the end date', function () {
    $customerService = serviceToUpdate();

    updateService($customerService, ['end_date' => '2026-06-16']);

    expect($customerService->fresh()->end_date->toDateString())->toBe('2026-06-16');
});

it('rejects changing the end date once the upcoming period charge exists, cancelled ones included', function (bool $cancelled) {
    $customerService = serviceToUpdate();
    Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
        ...($cancelled ? ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'x'] : []),
    ]);

    expect(fn () => updateService($customerService, ['end_date' => '2027-03-15']))
        ->toThrow(InvalidCustomerService::class, 'Yaklaşan dönemin borcu oluşmuş');

    expect($customerService->fresh()->end_date->toDateString())->toBe('2027-03-01');
})->with(['open' => false, 'cancelled' => true]);

it('still updates start date, price and notes when the upcoming period charge exists', function () {
    $customerService = serviceToUpdate();
    Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    updateService($customerService, ['start_date' => '2026-03-05', 'end_date' => '2027-03-01', 'price' => 420000, 'notes' => 'Not']);

    $customerService->refresh();

    expect($customerService->start_date->toDateString())->toBe('2026-03-05')
        ->and($customerService->price)->toBe(420000)
        ->and($customerService->notes)->toBe('Not');
});

it('only takes notes and item details for a cancelled service', function () {
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();
    $customerService = serviceToUpdate(['catalog_service_id' => $hosting->id, 'status' => 'cancelled', 'cancelled_on' => '2026-06-01']);
    $item = $customerService->items()->sole();

    foreach ([['price' => 500000], ['currency' => Currency::Usd], ['start_date' => '2026-03-02'], ['end_date' => '2027-03-02']] as $attributes) {
        expect(fn () => updateService($customerService, $attributes))
            ->toThrow(InvalidCustomerService::class, 'İptal edilmiş hizmetin fiyatı ve tarihleri değiştirilemez');
    }

    updateService($customerService, [
        'price' => 400000,
        'end_date' => '2027-03-01',
        'notes' => 'Arşiv notu',
        'items' => [$item->id => ['details' => ['server' => 'srv9']]],
    ]);

    expect($customerService->fresh()->notes)->toBe('Arşiv notu')
        ->and($customerService->fresh()->price)->toBe(400000)
        ->and($item->fresh()->details)->toBe(['server' => 'srv9']);
});

it('updates item details, drops keys of other types and keeps type and name', function () {
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create(['name' => 'Linux Hosting']);
    $customerService = serviceToUpdate(['catalog_service_id' => $hosting->id]);
    $item = $customerService->items()->sole();

    updateService($customerService, [
        'items' => [$item->id => ['details' => ['server' => 'srv2', 'domain' => 'logi.com.tr', 'quantity' => '9'], 'license_key' => 'IGNORED']],
    ]);

    $item->refresh();

    expect($item->details)->toEqual(['server' => 'srv2', 'domain' => 'logi.com.tr'])
        ->and($item->type)->toBe(ServiceType::Hosting)
        ->and($item->name)->toBe('Linux Hosting')
        ->and($item->license_key)->toBeNull();
});

it('stores the license key for license items only', function () {
    $license = CatalogService::factory()->ofType(ServiceType::License)->create();
    $customerService = serviceToUpdate(['catalog_service_id' => $license->id]);
    $item = $customerService->items()->sole();

    updateService($customerService, ['items' => [$item->id => ['details' => ['quantity' => '5'], 'license_key' => 'ABCD-1234']]]);

    expect($item->fresh()->license_key)->toBe('ABCD-1234')
        ->and($item->fresh()->details)->toBe(['quantity' => '5']);
});

it('rejects the item of another service and changes nothing', function () {
    $customerService = serviceToUpdate();
    $other = CustomerService::factory()->create();
    $foreignItem = $other->items()->firstOrFail();

    expect(fn () => updateService($customerService, ['price' => 500000, 'items' => [$foreignItem->id => ['details' => []]]]))
        ->toThrow(InvalidCustomerService::class, 'Kalem bilgisi');

    expect($customerService->fresh()->price)->toBe(400000)
        ->and(CustomerServiceItem::query()->find($foreignItem->id)->details)->toBe($foreignItem->details);
});
