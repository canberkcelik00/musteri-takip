<?php

use App\Enums\Permission;
use App\Livewire\Pages\Customers\Show;
use App\Livewire\Topbar\Search;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use App\Support\Ui\RecentCustomers;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

// --- Phone numbers ----------------------------------------------------------

it('finds a contact by phone however the number is written', function (string $term) {
    $customer = Customer::factory()->create(['short_name' => 'Telefonlu']);
    Contact::factory()->for($customer)->create(['phone' => '0532 123 45 67']);
    Customer::factory()->create(['short_name' => 'Başkası']);

    Livewire::test(Search::class)
        ->set('query', $term)
        ->assertSee('Telefonlu')
        ->assertDontSee('Başkası');
})->with(['spaced' => '0532 123 45 67', 'plain' => '05321234567', 'country code' => '+90 532 123 45 67', 'without zero' => '532-123']);

it('finds a contact whose phone is stored with a country code and dashes', function () {
    $customer = Customer::factory()->create(['short_name' => 'Telefonlu']);
    Contact::factory()->for($customer)->create(['phone' => '+90 (532) 123-45-67']);

    Livewire::test(Search::class)->set('query', '05321234567')->assertSee('Telefonlu');
});

it('still matches a short number as plain text', function () {
    $customer = Customer::factory()->create(['short_name' => 'Telefonlu']);
    Contact::factory()->for($customer)->create(['phone' => '0532 123 45 67']);
    Customer::factory()->create(['short_name' => 'Başkası']);

    Livewire::test(Search::class)->set('query', '123')->assertSee('Telefonlu')->assertDontSee('Başkası');
});

// --- Recently opened ----------------------------------------------------------

it('lists the customers opened last when the box is empty, newest first, without repeats and at most five', function () {
    $customers = Customer::factory()->count(7)->create();

    foreach ($customers as $customer) {
        RecentCustomers::remember($customer);
    }
    RecentCustomers::remember($customers[3]);

    expect(RecentCustomers::ids())->toBe([
        $customers[3]->id, $customers[6]->id, $customers[5]->id, $customers[4]->id, $customers[2]->id,
    ]);

    $search = Livewire::test(Search::class)->assertSee(__('ui.search.recent'));

    expect($search->instance()->recents()->pluck('id')->all())->toBe(RecentCustomers::ids());
});

it('remembers a customer when its card is opened', function () {
    $customer = Customer::factory()->create();

    Livewire::test(Show::class, ['customer' => $customer]);

    expect(RecentCustomers::ids())->toBe([$customer->id]);
});

it('leaves an archived customer out of the recent list', function () {
    $archived = Customer::factory()->archived()->create(['short_name' => 'Eski Firma']);
    $active = Customer::factory()->create(['short_name' => 'Güncel Firma']);
    RecentCustomers::remember($archived);
    RecentCustomers::remember($active);

    Livewire::test(Search::class)->assertSee('Güncel Firma')->assertDontSee('Eski Firma');
});

it('shows no list box when nothing was opened yet', function () {
    expect(Livewire::test(Search::class)->html())->not->toContain('ui-search-results');
});

it('reads the recent customers with a fixed number of queries', function () {
    $count = function (int $customers): int {
        session()->forget('recent_customers');

        foreach (Customer::factory()->count($customers)->create() as $customer) {
            RecentCustomers::remember($customer);
        }

        Livewire::test(Search::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Search::class);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count(1);

    expect($count(5))->toBe($count(2));
});

// --- Page shortcuts -------------------------------------------------------------

it('offers "Git: …" for a menu name the term starts', function () {
    Livewire::test(Search::class)
        ->set('query', 'işl')
        ->assertSee('Git: İşler')
        ->assertSee('href="'.route('app.tasks.index').'"', false)
        ->set('query', 'bug')
        ->assertSee('Git: Bugün');
});

it('keeps a page the user may not open out of the shortcuts', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::MoneyView->value);

    Livewire::actingAs(User::factory()->developer()->create())
        ->test(Search::class)
        ->set('query', 'ödem')
        ->assertDontSee('Git: Ödemeler');
});

it('does not say "no customer" when only a page matched', function () {
    Livewire::test(Search::class)->set('query', 'işl')->assertDontSee(__('ui.search.empty'));
});

// --- Task shortcut for a user without money access ---------------------------------

it('gives a developer "İş ekle" instead of "Ödeme al"', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::MoneyManage->value);
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    $html = Livewire::actingAs(User::factory()->developer()->create())
        ->test(Search::class)
        ->set('query', 'logi')
        ->assertSee('İş ekle')
        ->html();

    expect($html)->not->toContain('record-payment')
        ->toContain("name: 'edit-task', params: { customer: {$customer->id} }");
});
