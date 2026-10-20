<?php

use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Server;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-09-01 10:00:00');
});

it('pulls a WHM server, shows the hosting on the customer card and lets the renewal charge come from the normal rule', function () {
    Http::fake([
        '*/json-api/listpkgs*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listpkgs.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/listaccts*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listaccts.json')), 200, ['Content-Type' => 'application/json']),
    ]);
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(TestAction::make('pullFromServer')->table($server), [
            'packages' => [
                ['name' => 'gold', 'price' => '1.200,00', 'currency' => 'TRY'],
                ['name' => 'mini', 'price' => '300,00', 'currency' => 'TRY'],
            ],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('2 paket eklendi, 1 müşteri oluştu, 0 hizmet bağlandı, 1 askıda atlandı');

    $customer = Customer::query()->where('short_name', 'logi.com.tr')->sole();
    $service = CustomerService::query()->where('customer_id', $customer->id)->sole();

    expect($service->domains())->toBe(['logi.com.tr'])
        ->and($service->start_date->lessThanOrEqualTo(today()))->toBeTrue()
        ->and($service->end_date->greaterThan(today()))->toBeTrue()
        ->and(Charge::query()->count())->toBe(0);

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords([$service])
        ->assertSee('gold')
        ->assertSee('logi.com.tr');

    $this->travelTo($service->end_date->subDays(31)->setTime(9, 0));
    $this->artisan('app:process-customer-services')->assertSuccessful();
    expect(Charge::query()->count())->toBe(0);

    $this->travelTo($service->end_date->subDays(30)->setTime(9, 0));
    $this->artisan('app:process-customer-services')->assertSuccessful();

    $charge = Charge::query()->sole();

    expect($charge->is_renewal)->toBeTrue()
        ->and($charge->customer_id)->toBe($customer->id)
        ->and($charge->amount)->toBe(120000)
        ->and($charge->period_start->toDateString())->toBe($service->end_date->toDateString());
});
