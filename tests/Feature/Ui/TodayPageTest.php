<?php

use App\Actions\Scheduler\RecordSchedulerRun;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\SettingKey;
use App\Livewire\Pages\Today;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\RemoveFree;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Support\Settings;
use App\Support\Ui\Links;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
    fakePendingRelease(false);
    app(RecordSchedulerRun::class)->handle();
});

it('is the opening page of the glass interface and needs a signed-in user', function () {
    $this->get('/')->assertOk()->assertSee(__('navigation.today'));

    auth()->logout();

    $this->get('/')->assertRedirect(route('app.login'));
});

it('shows today\'s date under the title', function () {
    Livewire::test(Today::class)->assertSee('15 Haziran 2026, Pazartesi');
});

it('shows the empty state when there is nothing to do', function () {
    Livewire::test(Today::class)->assertSee(__('today.empty'))->assertDontSee(__('today.tasks_count', ['count' => 0]));
});

it('shows the four numbers, each linking to the list it is drawn from', function () {
    $overdue = Charge::factory()->create(['amount' => 150000, 'due_date' => '2026-06-01']);
    Charge::factory()->create(['amount' => 40000, 'due_date' => '2026-06-28']);
    Charge::factory()->create(['amount' => 5000, 'currency' => Currency::Usd, 'due_date' => '2026-09-01']);
    CustomerService::factory()->create(['start_date' => '2025-06-25', 'end_date' => '2026-06-25']);

    $html = Livewire::test(Today::class)->html();

    expect($html)
        ->toContain(__('today.total_debt'))->toContain('1.900 TL')->toContain('+ 50 USD')
        ->toContain(__('today.overdue'))->toContain('1.500 TL')->toContain(__('today.charge_count', ['count' => 1]))
        ->toContain(__('today.due_this_month'))->toContain('400 TL')
        ->toContain(__('today.renewals', ['days' => 30]))->toContain(__('today.service_count', ['count' => 1]))->toContain(__('today.nearest_renewal', ['date' => '25.06.2026']))
        ->toContain('href="'.e(route('app.charges.index', ['hideArchived' => 1])).'"')
        ->toContain('href="'.e(route('app.charges.index', ['onlyOverdue' => 1, 'hideArchived' => 1])).'"')
        ->toContain('href="'.e(route('app.charges.index', ['dueThisMonth' => 1, 'hideArchived' => 1])).'"')
        ->toContain('href="'.e(route('app.customers.index', ['renewalSoon' => 1])).'"');

    expect($overdue->customer)->not->toBeNull();
});

it('opens each stat link as a list that holds what the number counts and leaves archived customers out', function () {
    $overdue = Charge::factory()->create(['description' => 'Gecikmiş kalem', 'due_date' => '2026-06-01']);
    Charge::factory()->for(Customer::factory()->archived())->create(['description' => 'Arşivli kalem', 'due_date' => '2026-06-01']);
    Charge::factory()->create(['description' => 'Haziran kalemi', 'due_date' => '2026-06-28']);

    $this->get(route('app.charges.index', ['onlyOverdue' => 1, 'hideArchived' => 1]))
        ->assertSee('Gecikmiş kalem')
        ->assertDontSee('Arşivli kalem')
        ->assertDontSee('Haziran kalemi');

    $this->get(route('app.charges.index', ['dueThisMonth' => 1, 'hideArchived' => 1]))
        ->assertSee('Haziran kalemi')
        ->assertDontSee('Arşivli kalem');

    expect($overdue->customer->id)->toBeInt();
});

it('opens the renewal and free-decision links as filtered customer lists', function () {
    $soon = Customer::factory()->create(['short_name' => 'Yenileme Müşterisi']);
    CustomerService::factory()->for($soon)->create(['start_date' => '2025-06-25', 'end_date' => '2026-06-25']);
    Customer::factory()->create(['short_name' => 'Sakin Müşteri']);
    $free = Customer::factory()->create(['short_name' => 'Ücretsiz Müşteri']);
    CustomerService::factory()->for($free)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $this->get(route('app.customers.index', ['renewalSoon' => 1]))->assertSee('Yenileme Müşterisi')->assertDontSee('Sakin Müşteri');
    $this->get(route('app.customers.index', ['awaitingFreeDecision' => 1]))->assertSee('Ücretsiz Müşteri')->assertDontSee('Sakin Müşteri');
});

it('leaves the lines under the numbers out when there is nothing to count', function () {
    Livewire::test(Today::class)
        ->assertSee(__('today.service_count', ['count' => 0]))
        ->assertDontSee(__('today.nearest_renewal', ['date' => '']))
        ->assertDontSee('+ ');
});

it('lists the three kinds of work as the rows of one table, in priority order, with the count in the heading', function () {
    Charge::factory()->for(Customer::factory()->create(['short_name' => 'Yakın Müşteri']))->create(['description' => 'Yakın borç', 'due_date' => '2026-06-20']);
    Charge::factory()->for(Customer::factory()->create(['short_name' => 'Geç Müşteri']))->create(['description' => 'Geç borç', 'due_date' => '2026-06-01']);
    CustomerService::factory()->for(Customer::factory()->create(['short_name' => 'Ücretsiz Müşteri']))->free()->create(['name' => 'Bedava Hizmet', 'start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $html = Livewire::test(Today::class)->assertSee(__('today.tasks_count', ['count' => 3]))->html();

    $positions = array_map(fn (string $name) => strpos($html, $name), ['Geç Müşteri', 'Yakın Müşteri', 'Ücretsiz Müşteri']);

    expect($positions)->toBe(collect($positions)->sort()->values()->all())->and($positions)->each->not->toBeFalse();
});

it('puts "kısmi ödendi" after a partly paid charge and "ücretsiz" after a free service, in the subject', function () {
    $charge = Charge::factory()->create(['description' => 'Hosting', 'amount' => 100000, 'due_date' => '2026-06-01']);
    $payment = Payment::factory()->for($charge->customer)->create(['amount' => 30000]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount' => 30000]);
    CustomerService::factory()->free()->create(['name' => 'Alan adı', 'start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    Livewire::test(Today::class)->assertSee('Hosting · kısmi ödendi')->assertSee('Alan adı · ücretsiz');
});

it('shows "Karar bekliyor" as plain text and a dash instead of an amount for a free service', function () {
    CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $html = Livewire::test(Today::class)->html();

    expect($html)->toContain(__('today.awaiting_decision'))->not->toContain('ui-badge');
});

it('colors the status badge red for overdue and amber for due soon', function () {
    Charge::factory()->create(['due_date' => '2026-06-01']);
    Charge::factory()->create(['due_date' => '2026-06-20']);

    $html = Livewire::test(Today::class)->html();

    expect($html)->toContain('ui-badge ui-badge-red')->toContain('ui-badge ui-badge-amber')->toContain(__('charges.overdue_days', ['count' => 14]))->toContain(__('charges.due_in_days', ['count' => 5]));
});

it('moves the subject, date and state under the customer name below md', function () {
    Charge::factory()->create(['description' => 'Hosting', 'due_date' => '2026-06-01']);

    expect(Livewire::test(Today::class)->html())->toContain('ui-below-md')->toContain('Hosting · 01.06.2026');
});

it('opens the payment window from an overdue or due-soon row with the customer and the currency', function () {
    $overdue = Charge::factory()->create(['due_date' => '2026-06-01', 'currency' => Currency::Usd]);

    expect(Livewire::test(Today::class)->html())->toContain("name: 'record-payment', params: { customer: {$overdue->customer_id}, currency: 'USD' }");
});

it('does not list an archived customer\'s overdue charge', function () {
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-06-01', 'description' => 'Arşivli borç']);

    Livewire::test(Today::class)->assertDontSee('Arşivli borç')->assertSee(__('today.empty'));
});

it('caps the overdue section at 10 rows and links to the full filtered list', function () {
    Charge::factory()->count(12)->create(['due_date' => '2026-06-01']);

    $html = Livewire::test(Today::class)->assertSee(__('today.tasks_count', ['count' => 12]))->html();

    expect($html)
        ->toContain(__('today.section_overdue').': '.__('today.view_all', ['count' => 12]))
        ->toContain('href="'.e(route('app.charges.index', ['onlyOverdue' => 1, 'hideArchived' => 1])).'"');
});

it('puts a WhatsApp link next to "Ödeme al" on charge rows with a usable phone number, not on free rows', function () {
    $charge = Charge::factory()->create(['due_date' => '2026-06-01']);
    Contact::factory()->for($charge->customer)->primary()->create(['phone' => '0532 111 22 33']);
    CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $html = Livewire::test(Today::class)->html();

    expect(substr_count($html, 'wa.me/905321112233'))->toBe(1);

    Contact::query()->update(['phone' => 'yok']);

    expect(Livewire::test(Today::class)->html())->not->toContain('wa.me');
});

it('opens the confirmation and the make-paid windows from a free-decision row', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $html = Livewire::test(Today::class)->html();

    expect($html)
        ->toContain("name: 'confirm', params: { action: 'confirm-free-continuation', id: {$service->id} }")
        ->toContain("name: 'remove-free', params: { service: {$service->id} }");
});

it('confirms free continuation from the row, removing it from the list', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    Livewire::test(Confirm::class, ['action' => 'confirm-free-continuation', 'id' => $service->id])
        ->call('confirm')
        ->assertDispatched('customer-services-changed')
        ->assertDispatched('notify', message: __('customer_services.free_continuation_confirmed'), tone: 'success');

    expect($service->fresh()->isAwaitingFreeDecision())->toBeFalse();

    Livewire::test(Today::class)->assertSee(__('today.empty'));
});

it('confirms nothing when the service was cancelled in another tab after the row was drawn', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $service->update(['status' => CustomerServiceStatus::Cancelled]);

    Livewire::test(Confirm::class, ['action' => 'confirm-free-continuation', 'id' => $service->id])
        ->call('confirm')
        ->assertDispatched('notify', message: __('customer_services.free_action_not_applied'), tone: 'danger');
});

it('makes a service paid from the row window, creating a charge, and a second time creates none', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20', 'price' => 400000]);

    Livewire::test(RemoveFree::class, ['service' => $service->id])->set('price', '4.000')->call('save')->assertHasNoErrors();

    expect($service->fresh()->is_free)->toBeFalse()->and($service->fresh()->charges()->count())->toBe(1);

    Livewire::test(RemoveFree::class, ['service' => $service->id])->set('price', '4.000')->call('save')
        ->assertDispatched('notify', message: __('customer_services.free_action_not_applied'), tone: 'danger');

    expect($service->fresh()->charges()->count())->toBe(1);
});

describe('hosting quota rows', function () {
    it('shows a quota row with the customer, the subject, the level and a button, after the charges', function () {
        $server = Server::factory()->whm()->create();
        $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
        UsageScenario::usage($server, 'logi');
        UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 100, 100);
        Charge::factory()->for(Customer::factory()->create(['short_name' => 'Gecikmis Musteri']))->create(['due_date' => today()->subDays(3)]);

        $html = Livewire::test(Today::class)
            ->assertSee('info@logi.com.tr kotası %100')
            ->assertSee('Disk %94 · logi.com.tr')
            ->assertSee(__('usage.view'))
            ->assertSee(__('today.tasks_count', ['count' => 3]))
            ->html();

        expect(strpos($html, 'Gecikmis Musteri'))->toBeLessThan(strpos($html, 'Disk %94'))
            ->and($html)->toContain('href="'.route('app.customers.show', $service->customer).'"')
            ->and($html)->toContain("name: 'view-usage'");
    });

    it('says how many shared customers a cPanel disk warning concerns', function () {
        $server = Server::factory()->cpanel()->create(['username' => 'logi']);
        UsageScenario::linkedService($server, 'logi.com.tr');
        UsageScenario::linkedService($server, 'ikinci.com');
        UsageScenario::usage($server, 'logi');

        Livewire::test(Today::class)->assertSee('2 müşteriyle paylaşılıyor')->assertSee(__('today.tasks_count', ['count' => 1]));
    });

    it('notes how many quota warnings did not fit in the list', function () {
        $server = Server::factory()->whm()->create();
        UsageScenario::linkedService($server, 'logi');
        UsageScenario::usage($server, 'logi', []);

        foreach (range(1, 12) as $number) {
            UsageScenario::mailbox($server, 'logi', "kutu{$number}@logi.com.tr", 95, 100);
        }

        Livewire::test(Today::class)->assertSee(__('today.tasks_count', ['count' => 12]))->assertSee('Hosting kotası: 2 uyarı daha');
    });

    it('shows a reseller total as a server row with a link to the servers list', function () {
        $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);
        ServerUsage::factory()->create(['server_id' => $server->id, 'resources' => [
            ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => 47 * 1073741824, 'limit' => 50 * 1073741824, 'is_bytes' => true, 'limit_read' => true],
        ]]);

        $html = Livewire::test(Today::class)->assertSee('Sunucu: Ana sunucu')->assertSee(__('today.go_to_server'))->html();

        expect($html)->toContain('href="'.e(Links::servers()).'"');
    });
});

describe('scheduler warning', function () {
    beforeEach(function () {
        $this->travelTo('2026-10-13 14:00:00');
    });

    it('shows the warning once the scheduler has not run for over an hour and hides it after a run', function () {
        $this->artisan('app:process-customer-services')->assertSuccessful();

        $this->travelTo('2026-10-13 15:00:00');
        $this->get('/')->assertOk()->assertDontSee('ui-error-box', false);

        $this->travelTo('2026-10-13 15:01:00');
        $this->get('/')->assertSee('Zamanlayıcı 1 saatten uzun süredir çalışmıyor (son çalışma: 13.10.2026 14:00).', false);

        $this->artisan('app:process-customer-services')->assertSuccessful();
        $this->get('/')->assertOk()->assertDontSee('ui-error-box', false);
    });

    it('warns when the scheduler never ran', function () {
        Setting::query()->delete();
        app()->forgetInstance(Settings::class);

        Livewire::test(Today::class)->assertSee(__('scheduler.never_ran'));
    });

    it('names a pending release instead of the cron', function () {
        fakePendingRelease(true);
        $this->travelTo('2026-10-13 15:30:00');

        Livewire::test(Today::class)->assertSee(__('scheduler.pending_release'))->assertDontSee('13.10.2026 14:00');
    });

    it('lists the services that failed in the last run with links to their cards', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        $service = CustomerService::factory()->for($customer)->create(['name' => 'Hosting']);
        app(RecordSchedulerRun::class)->handle([$service->id]);

        $html = Livewire::test(Today::class)
            ->assertSee('Son zamanlayıcı çalışmasında 1 hizmet işlenemedi.')
            ->assertSee('Logi · Hosting')
            ->assertSee(__('scheduler.failed_services_log'))
            ->html();

        expect($html)->toContain('href="'.route('app.customers.show', $customer).'"');
    });

    it('names at most five failed services and counts the rest', function () {
        $services = CustomerService::factory()->count(7)->create();
        app(RecordSchedulerRun::class)->handle($services->modelKeys());

        $component = Livewire::test(Today::class)->assertSee('ve 2 hizmet daha');

        $services->take(5)->each(fn (CustomerService $service) => $component->assertSee($service->name));
        $services->skip(5)->each(fn (CustomerService $service) => $component->assertDontSee($service->name));
    });

    it('shows nothing while the scheduler is healthy', function () {
        app(RecordSchedulerRun::class)->handle();

        $this->get('/')->assertOk()->assertDontSee('ui-error-box', false);
    });
});

it('refreshes after a payment, a charge or a service changed in a window', function () {
    $today = Livewire::test(Today::class)->assertSee(__('today.empty'));

    Charge::factory()->create(['description' => 'Yeni gecikmiş', 'due_date' => '2026-06-01']);

    $today->dispatch('charges-changed')->assertSee('Yeni gecikmiş');
    $today->dispatch('payments-changed')->dispatch('customer-services-changed')->assertHasNoErrors();
});

describe('for a developer', function () {
    beforeEach(function () {
        $this->developer = User::factory()->developer()->create();
        $this->actingAs($this->developer);
    });

    it('opens the home page without a redirect and without any money', function () {
        $charge = Charge::factory()->create(['amount' => 150000, 'due_date' => '2026-06-01', 'description' => 'Gizli borc']);
        CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

        $html = $this->get('/')->assertOk()->assertSee(__('today.tasks_heading'))->getContent();

        expect($html)
            ->not->toContain('ui-stats')
            ->not->toContain('Gizli borc')
            ->not->toContain('1.500')
            ->not->toContain(__('today.col_amount'))
            ->not->toContain(__('today.total_debt'))
            ->not->toContain('record-payment');
    });

    it('lists my own open tasks with their state and no one else\'s', function () {
        Task::factory()->create(['title' => 'Logi form maili', 'assigned_to' => $this->developer->id, 'due_date' => '2026-06-12']);
        Task::factory()->dueToday()->create(['title' => 'Bugunluk is', 'assigned_to' => $this->developer->id]);
        Task::factory()->create(['title' => 'Baskasinin isi', 'assigned_to' => User::factory()->developer()->create()->id]);

        Livewire::test(Today::class)
            ->assertSee('Logi form maili')
            ->assertSee(__('tasks.overdue_days', ['count' => 3]))
            ->assertSee('Bugunluk is')
            ->assertSee(__('tasks.due_today'))
            ->assertDontSee('Baskasinin isi')
            ->assertSee(__('today.tasks_count', ['count' => 2]));
    });

    it('shows the quota warnings and the empty state otherwise', function () {
        Livewire::test(Today::class)->assertSee(__('today.empty'));

        $server = Server::factory()->whm()->create();
        UsageScenario::linkedService($server, 'logi');
        UsageScenario::usage($server, 'logi', [UsageScenario::resource('disk_usage', 92, 100, true)]);

        Livewire::test(Today::class)->assertDontSee(__('today.empty'))->assertSee('Disk %92');
    });

    it('drops a task from the list when it is finished', function () {
        $task = Task::factory()->create(['title' => 'Bitecek is', 'assigned_to' => $this->developer->id]);

        Livewire::test(Today::class)
            ->assertSee('Bitecek is')
            ->call('completeTask', $task->id)
            ->assertDontSee('Bitecek is')
            ->assertSee(__('today.empty'));

        expect($task->fresh()->completed_by)->toBe($this->developer->id);
    });

    it('cannot finish someone else\'s task from the page', function () {
        $task = Task::factory()->create(['assigned_to' => User::factory()->developer()->create()->id]);

        Livewire::test(Today::class)->call('completeTask', $task->id)->assertNotFound();

        expect($task->fresh()->completed_at)->toBeNull();
    });

    it('hides the scheduler warning from a user who may not change settings', function () {
        fakePendingRelease(true);

        Livewire::test(Today::class)->assertDontSee(__('scheduler.pending_release'));
    });
});

it('shows an administrator the tasks assigned to them beside the money rows', function () {
    $admin = auth()->user();
    Task::factory()->create(['title' => 'Yonetici isi', 'assigned_to' => $admin->id]);
    Charge::factory()->create(['due_date' => '2026-06-01', 'description' => 'Gecikmis borc']);

    $html = Livewire::test(Today::class)->assertSee('Yonetici isi')->assertSee('Gecikmis borc')->html();

    expect(strpos($html, 'Gecikmis borc'))->toBeLessThan(strpos($html, 'Yonetici isi'));
});

it('does not summarize money for a user without money.view', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs(User::factory()->developer()->create());
    Livewire::test(Today::class)->assertOk();

    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, '`charges`')))->toBeEmpty();
});
