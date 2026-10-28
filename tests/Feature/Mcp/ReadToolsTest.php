<?php

use App\Actions\Account\CreateMcpToken;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\McpAccess;
use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Mcp\Servers\MusteriTakipServer;
use App\Mcp\Tools\CustomerCard;
use App\Mcp\Tools\ListCalendar;
use App\Mcp\Tools\ListCharges;
use App\Mcp\Tools\ListPayments;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\SearchCustomers;
use App\Mcp\Tools\TodaySummary;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Spatie\Permission\Models\Role;

/**
 * The Claude server acting as the user, through a real token of the given access (as the endpoint does).
 */
function mcpAs(User $user, McpAccess $access = McpAccess::ReadOnly): PendingTestResponse
{
    app(CreateMcpToken::class)->handle($user, 'Ofis', $access);

    return MusteriTakipServer::actingAs($user->withAccessToken($user->tokens()->latest('id')->firstOrFail()));
}

beforeEach(function () {
    $this->travelTo('2026-10-28 10:00');
});

it('lists every read tool for an administrator and leaves the money tools out for a developer', function () {
    $all = [TodaySummary::class, SearchCustomers::class, CustomerCard::class, ListCharges::class, ListPayments::class, ListTasks::class, ListCalendar::class];

    mcpAs(User::factory()->admin()->create())->tools()->assertRegistered($all);

    mcpAs(User::factory()->developer()->create())->tools()
        ->assertRegistered([TodaySummary::class, SearchCustomers::class, CustomerCard::class, ListTasks::class, ListCalendar::class])
        ->assertNotRegistered([ListCharges::class, ListPayments::class]);
});

it('lists no tool without a token carrying the read ability', function () {
    MusteriTakipServer::actingAs(User::factory()->admin()->create())->tools()
        ->assertNotRegistered([TodaySummary::class, SearchCustomers::class, ListCharges::class]);
});

it('does not let a developer call a money tool', function (string $tool) {
    Charge::factory()->create();

    mcpAs(User::factory()->developer()->create())->tool($tool)->assertHasErrors();
})->with([ListCharges::class, ListPayments::class]);

it('does not let a user without tasks.manage list tasks', function () {
    $user = User::factory()->developer()->create();
    Role::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);
    $user->refresh();

    mcpAs($user)->tools()->assertNotRegistered(ListTasks::class);
    mcpAs($user)->tool(ListTasks::class)->assertHasErrors();
});

it('writes every call to the mcp log channel without the arguments', function () {
    $user = User::factory()->admin()->create(['email' => 'sahip@example.com']);

    Log::shouldReceive('channel')->once()->with('mcp')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Tool call'
        && $context === ['user_id' => $user->id, 'user' => 'sahip@example.com', 'tool' => 'search-customers', 'token' => 'Ofis']);

    mcpAs($user)->tool(SearchCustomers::class, ['query' => 'gizli arama'])->assertOk();
});

it('answers a tool call over the endpoint with the token', function () {
    $user = User::factory()->admin()->create();
    Customer::factory()->create(['short_name' => 'Acme Yazılım']);
    $token = app(CreateMcpToken::class)->handle($user, 'Ofis', McpAccess::ReadOnly);

    $this->withToken($token)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'search-customers', 'arguments' => ['query' => 'acme']]])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.customers.0.short_name', 'Acme Yazılım');
});

it('summarises today with money per currency for an administrator', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create(['short_name' => 'Acme']);
    Charge::factory()->for($customer)->create(['amount' => 150000, 'currency' => Currency::Try, 'due_date' => '2026-10-20']);
    Charge::factory()->for($customer)->create(['amount' => 20000, 'currency' => Currency::Usd, 'due_date' => '2026-10-30']);
    Task::factory()->create(['title' => 'Yedek al', 'assigned_to' => $admin->id, 'due_date' => '2026-10-27']);

    mcpAs($admin)->tool(TodaySummary::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('today', '2026-10-28')
            ->where('money.open_total', [
                ['currency' => 'TRY', 'amount_minor' => 150000, 'formatted' => '1.500 TL'],
                ['currency' => 'USD', 'amount_minor' => 20000, 'formatted' => '200 USD'],
            ])
            ->where('money.overdue.count', 1)
            ->where('overdue_charges.total', 1)
            ->where('overdue_charges.items.0.customer', 'Acme')
            ->where('overdue_charges.items.0.days_overdue', 8)
            ->where('due_soon_charges.items.0.remaining.formatted', '200 USD')
            ->where('my_open_tasks.items.0.title', 'Yedek al')
            ->where('my_open_tasks.items.0.overdue', true)
            ->etc());
});

it('leaves money out of today for a developer', function () {
    $developer = User::factory()->developer()->create();
    Charge::factory()->create(['due_date' => '2026-10-20']);
    Task::factory()->create(['title' => 'Siteyi güncelle', 'assigned_to' => $developer->id]);

    mcpAs($developer)->tool(TodaySummary::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('money', null)
            ->where('overdue_charges', ['total' => 0, 'items' => []])
            ->where('my_open_tasks.items.0.title', 'Siteyi güncelle')
            ->etc());
});

it('finds customers by domain and phone and leaves archived ones out unless asked', function () {
    $acme = Customer::factory()->create(['short_name' => 'Acme']);
    CustomerServiceItem::factory()
        ->for(CustomerService::factory()->for($acme))
        ->ofType(ServiceType::Domain)
        ->create(['details' => ['domain' => 'acme.com.tr']]);
    Contact::factory()->for($acme)->primary()->create(['name' => 'Ayşe Yılmaz', 'phone' => '0532 123 45 67']);
    Customer::factory()->archived()->create(['short_name' => 'Acme Eski']);

    $admin = User::factory()->admin()->create();

    mcpAs($admin)->tool(SearchCustomers::class, ['query' => 'acme.com'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 1)
            ->where('customers.0.short_name', 'Acme')
            ->where('customers.0.domains', fn ($domains): bool => collect($domains)->contains('acme.com.tr'))
            ->where('customers.0.primary_contact.name', 'Ayşe Yılmaz')
            ->etc());

    mcpAs($admin)->tool(SearchCustomers::class, ['query' => '+90 532 123 4567'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('customers.0.id', $acme->id)->etc());

    mcpAs($admin)->tool(SearchCustomers::class, ['query' => 'acme'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 1)->etc());

    mcpAs($admin)->tool(SearchCustomers::class, ['query' => 'acme', 'include_archived' => true])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 2)
            ->where('customers.1.archived', true)
            ->etc());
});

it('returns at most 50 rows and tells the full count', function () {
    Customer::factory()->count(55)->sequence(fn ($sequence) => ['short_name' => 'Müşteri '.$sequence->index])->create();
    Charge::factory()->count(55)->create();

    $admin = User::factory()->admin()->create();

    mcpAs($admin)->tool(SearchCustomers::class, ['query' => 'Müşteri'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 55)->has('customers', 50));

    mcpAs($admin)->tool(ListCharges::class)
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 55)->has('charges', 50)->etc());
});

it('shows the customer card with money only to a user who may see money', function () {
    $customer = Customer::factory()->create(['short_name' => 'Acme', 'notes' => 'gizli not', 'tax_number' => '1234567890']);
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz']);
    $service = CustomerService::factory()->for($customer)->create(['name' => 'Kurumsal Hosting', 'price' => 300000, 'currency' => Currency::Try]);
    CustomerServiceItem::factory()->for($service)->withLicenseKey()->create(['license_key' => 'GIZLI-LISANS-ANAHTARI']);
    Charge::factory()->for($customer)->create(['amount' => 100000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Eur]);
    Payment::factory()->for($customer)->create(['amount' => 7000, 'currency' => Currency::Usd]);

    mcpAs(User::factory()->admin()->create())->tool(CustomerCard::class, ['customer_id' => $customer->id])
        ->assertOk()
        ->assertDontSee(['gizli not', '1234567890', 'GIZLI-LISANS-ANAHTARI'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('short_name', 'Acme')
            ->where('contacts.0.name', 'Ayşe Yılmaz')
            ->where('services.0.name', 'Kurumsal Hosting')
            ->where('services.0.price.formatted', '3.000 TL')
            ->where('open_debt', [
                ['currency' => 'TRY', 'amount_minor' => 100000, 'formatted' => '1.000 TL'],
                ['currency' => 'EUR', 'amount_minor' => 5000, 'formatted' => '50 EUR'],
            ])
            ->where('credit_balance.0.formatted', '70 USD')
            ->has('open_charges', 2)
            ->etc());

    mcpAs(User::factory()->developer()->create())->tool(CustomerCard::class, ['customer_id' => $customer->id])
        ->assertOk()
        ->assertDontSee(['1.000 TL', '3.000 TL'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('services.0.name', 'Kurumsal Hosting')
            ->missing('services.0.price')
            ->missing('open_debt')
            ->missing('credit_balance')
            ->missing('open_charges')
            ->etc());
});

it('refuses an unknown customer card', function () {
    mcpAs(User::factory()->admin()->create())->tool(CustomerCard::class, ['customer_id' => 999])->assertHasErrors();
    mcpAs(User::factory()->admin()->create())->tool(CustomerCard::class)->assertHasErrors();
});

it('lists open charges of customers not archived with a total per currency', function () {
    $acme = Customer::factory()->create(['short_name' => 'Acme']);
    Charge::factory()->for($acme)->create(['amount' => 100000, 'currency' => Currency::Try, 'due_date' => '2026-10-10']);
    Charge::factory()->for($acme)->create(['amount' => 30000, 'currency' => Currency::Usd, 'due_date' => '2026-11-15']);
    Charge::factory()->for($acme)->paid()->create(['due_date' => '2026-09-01']);
    Charge::factory()->for(Customer::factory()->archived())->create(['amount' => 99900]);

    $admin = User::factory()->admin()->create();

    mcpAs($admin)->tool(ListCharges::class)
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 2)
            ->where('remaining_open_totals', [
                ['currency' => 'TRY', 'amount_minor' => 100000, 'formatted' => '1.000 TL'],
                ['currency' => 'USD', 'amount_minor' => 30000, 'formatted' => '300 USD'],
            ])
            ->where('charges.0.due_date', '2026-10-10')
            ->where('charges.0.days_overdue', 18)
            ->where('charges.1.status', ChargeStatus::Open->value)
            ->etc());

    mcpAs($admin)->tool(ListCharges::class, ['only_overdue' => true])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 1)->where('charges.0.amount.currency', 'TRY')->etc());

    mcpAs($admin)->tool(ListCharges::class, ['due_from' => '2026-11-01', 'due_to' => '2026-11-30'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 1)->where('charges.0.amount.currency', 'USD')->etc());

    mcpAs($admin)->tool(ListCharges::class, ['currency' => 'USD'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 1)->etc());

    mcpAs($admin)->tool(ListCharges::class, ['include_archived' => true])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 3)->etc());

    mcpAs($admin)->tool(ListCharges::class, ['status' => 'all', 'customer_id' => $acme->id])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 3)->etc());
});

it('refuses invalid input', function (string $tool, array $arguments) {
    mcpAs(User::factory()->admin()->create())->tool($tool, $arguments)->assertHasErrors();
})->with([
    'unknown currency' => [ListCharges::class, ['currency' => 'GBP']],
    'unknown status' => [ListCharges::class, ['status' => 'late']],
    'wrong date' => [ListCharges::class, ['due_from' => '28.10.2026']],
    'range backwards' => [ListPayments::class, ['from' => '2026-10-28', 'to' => '2026-10-01']],
    'short search' => [SearchCustomers::class, ['query' => 'a']],
    'unknown who' => [ListTasks::class, ['who' => 'them']],
    'calendar without dates' => [ListCalendar::class, []],
]);

it('lists payments in a date range with a total per currency', function () {
    $acme = Customer::factory()->create(['short_name' => 'Acme']);
    Payment::factory()->for($acme)->create(['amount' => 50000, 'currency' => Currency::Try, 'paid_on' => '2026-10-05', 'note' => 'Havale']);
    Payment::factory()->for($acme)->create(['amount' => 25000, 'currency' => Currency::Try, 'paid_on' => '2026-10-20']);
    Payment::factory()->for($acme)->create(['amount' => 10000, 'currency' => Currency::Usd, 'paid_on' => '2026-10-21']);
    Payment::factory()->create(['amount' => 99900, 'paid_on' => '2026-09-30']);

    mcpAs(User::factory()->admin()->create())->tool(ListPayments::class, ['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 3)
            ->where('totals', [
                ['currency' => 'TRY', 'amount_minor' => 75000, 'formatted' => '750 TL'],
                ['currency' => 'USD', 'amount_minor' => 10000, 'formatted' => '100 USD'],
            ])
            ->where('payments.0.paid_on', '2026-10-21')
            ->where('payments.2.note', 'Havale')
            ->where('payments.2.customer', 'Acme')
            ->etc());
});

it('lists my open tasks by default and everyone\'s on request', function () {
    $user = User::factory()->developer()->create();
    Task::factory()->create(['title' => 'Benim işim', 'assigned_to' => $user->id, 'due_date' => '2026-10-30']);
    Task::factory()->completed()->create(['title' => 'Bitmiş iş', 'assigned_to' => $user->id]);
    Task::factory()->create(['title' => 'Başkasının işi']);

    mcpAs($user)->tool(ListTasks::class)
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 1)
            ->where('tasks.0.title', 'Benim işim')
            ->where('tasks.0.due_date', '2026-10-30')
            ->etc());

    mcpAs($user)->tool(ListTasks::class, ['who' => 'all'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 2)->etc());

    mcpAs($user)->tool(ListTasks::class, ['status' => 'done'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('tasks.0.title', 'Bitmiş iş')->etc());
});

it('lists the calendar of a range with the sources the user may see', function () {
    $customer = Customer::factory()->create(['short_name' => 'Acme']);
    Charge::factory()->for($customer)->create(['due_date' => '2026-11-03']);
    Payment::factory()->for($customer)->create(['created_at' => '2026-11-02 14:30']);

    mcpAs(User::factory()->admin()->create())->tool(ListCalendar::class, ['from' => '2026-11-01', 'to' => '2026-11-30'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 2)
            ->where('entries.0.date', '2026-11-02')
            ->where('entries.0.category', 'payment')
            ->where('entries.0.recorded', true)
            ->where('entries.1.category', 'charge_due')
            ->etc());

    mcpAs(User::factory()->developer()->create())->tool(ListCalendar::class, ['from' => '2026-11-01', 'to' => '2026-11-30'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('total', 0)->etc());
});

it('limits the calendar range to 92 days', function () {
    $admin = User::factory()->admin()->create();

    mcpAs($admin)->tool(ListCalendar::class, ['from' => '2026-11-01', 'to' => '2027-01-31'])->assertOk();

    mcpAs($admin)->tool(ListCalendar::class, ['from' => '2026-11-01', 'to' => '2027-02-01'])
        ->assertHasErrors([__('mcp.calendar_range', ['days' => 92])]);
});
