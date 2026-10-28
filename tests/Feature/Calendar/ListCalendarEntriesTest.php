<?php

use App\Actions\Calendar\ListCalendarEntries;
use App\Enums\CalendarCategory;
use App\Enums\EventRepeat;
use App\Enums\Permission;
use App\Models\CalendarEvent;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Support\Calendar\CalendarEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function calendarEntries(User $user, string $from, string $to, ?array $categories = null, bool $onlyMine = false): array
{
    return app(ListCalendarEntries::class)
        ->handle($user, Carbon::parse($from), Carbon::parse($to), $categories, $onlyMine)
        ->all();
}

function calendarDates(array $entries): array
{
    return array_map(fn (CalendarEntry $entry): string => $entry->date->toDateString(), $entries);
}

beforeEach(function () {
    $this->travelTo('2026-10-28 10:00:00');
    $this->admin = User::factory()->admin()->create();
});

describe('events', function () {
    it('lists a single event only inside the range', function () {
        CalendarEvent::factory()->create(['title' => 'İçeride', 'starts_on' => '2026-11-03']);
        CalendarEvent::factory()->create(['title' => 'Dışarıda', 'starts_on' => '2026-11-10']);

        $entries = calendarEntries($this->admin, '2026-11-02', '2026-11-08');

        expect(collect($entries)->pluck('title')->all())->toBe(['İçeride'])
            ->and($entries[0]->isEditable())->toBeTrue()
            ->and($entries[0]->category)->toBe(CalendarCategory::Event);
    });

    it('shows the times of a timed event and none for an all-day event', function () {
        CalendarEvent::factory()->create(['title' => 'Saatli', 'starts_on' => '2026-11-03', 'start_time' => '14:00:00', 'end_time' => '15:30:00']);
        CalendarEvent::factory()->allDay()->create(['title' => 'Tüm gün', 'starts_on' => '2026-11-03']);

        [$allDay, $timed] = calendarEntries($this->admin, '2026-11-03', '2026-11-03');

        expect($allDay->title)->toBe('Tüm gün')
            ->and($allDay->allDay)->toBeTrue()
            ->and($allDay->start)->toBeNull()
            ->and($timed->start)->toBe('14:00')
            ->and($timed->end)->toBe('15:30');
    });

    it('opens a weekly event on every week of the range, also when it started long before', function () {
        CalendarEvent::factory()->repeating(EventRepeat::Weekly)->create(['starts_on' => '2026-01-05']);

        $entries = calendarEntries($this->admin, '2026-11-01', '2026-11-30');

        expect(calendarDates($entries))->toBe(['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23', '2026-11-30']);
    });

    it('does not open a repeat before the first date or after repeat_until', function () {
        CalendarEvent::factory()->repeating(EventRepeat::Weekly)->create(['starts_on' => '2026-11-09', 'repeat_until' => '2026-11-20']);

        expect(calendarDates(calendarEntries($this->admin, '2026-11-01', '2026-11-30')))->toBe(['2026-11-09', '2026-11-16']);
    });

    it('keeps a monthly event on its day and falls on the last day of a shorter month', function () {
        CalendarEvent::factory()->repeating(EventRepeat::Monthly)->create(['starts_on' => '2026-01-31']);

        $entries = calendarEntries($this->admin, '2026-01-01', '2026-06-30');

        expect(calendarDates($entries))->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30']);
    });

    it('opens a monthly event in a later year', function () {
        CalendarEvent::factory()->repeating(EventRepeat::Monthly)->create(['starts_on' => '2024-12-15']);

        expect(calendarDates(calendarEntries($this->admin, '2026-02-01', '2026-02-28')))->toBe(['2026-02-15']);
    });

    it('lists a series that ended before the range as nothing', function () {
        CalendarEvent::factory()->repeating(EventRepeat::Weekly)->create(['starts_on' => '2026-01-05', 'repeat_until' => '2026-03-01']);

        expect(calendarEntries($this->admin, '2026-11-01', '2026-11-30'))->toBeEmpty();
    });

    it('puts all-day entries first and then orders by time', function () {
        CalendarEvent::factory()->create(['title' => 'Öğleden sonra', 'starts_on' => '2026-11-03', 'start_time' => '15:00:00', 'end_time' => '16:00:00']);
        CalendarEvent::factory()->create(['title' => 'Sabah', 'starts_on' => '2026-11-03', 'start_time' => '09:00:00', 'end_time' => '10:00:00']);
        CalendarEvent::factory()->allDay()->create(['title' => 'Tüm gün', 'starts_on' => '2026-11-03']);
        CalendarEvent::factory()->create(['title' => 'Önceki gün', 'starts_on' => '2026-11-02', 'start_time' => '18:00:00', 'end_time' => '19:00:00']);

        $titles = collect(calendarEntries($this->admin, '2026-11-01', '2026-11-30'))->pluck('title')->all();

        expect($titles)->toBe(['Önceki gün', 'Tüm gün', 'Sabah', 'Öğleden sonra']);
    });

    it('names the customer of an event', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        CalendarEvent::factory()->for($customer)->create(['starts_on' => '2026-11-03']);

        expect(calendarEntries($this->admin, '2026-11-03', '2026-11-03')[0]->detail)->toBe('Logi');
    });
});

describe('only mine', function () {
    it('keeps the events I created or attend and the tasks assigned to me, and nothing else', function () {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        CalendarEvent::factory()->create(['title' => 'Benim', 'starts_on' => '2026-11-03', 'created_by' => $me->id]);
        CalendarEvent::factory()->create(['title' => 'Katıldığım', 'starts_on' => '2026-11-03', 'created_by' => $other->id])->attendees()->attach($me);
        CalendarEvent::factory()->create(['title' => 'Başkasının', 'starts_on' => '2026-11-03', 'created_by' => $other->id]);
        Task::factory()->create(['title' => 'Bana iş', 'assigned_to' => $me->id, 'due_date' => '2026-11-03']);
        Task::factory()->create(['title' => 'Başkasına iş', 'assigned_to' => $other->id, 'due_date' => '2026-11-03']);
        CustomerService::factory()->create(['end_date' => '2026-11-03']);
        Charge::factory()->create(['due_date' => '2026-11-03']);

        $titles = collect(calendarEntries($me, '2026-11-03', '2026-11-03', onlyMine: true))->pluck('title')->sort()->values()->all();

        expect($titles)->toBe(['Bana iş', 'Benim', 'Katıldığım']);
    });
});

describe('category filter', function () {
    it('lists only the chosen categories', function () {
        CalendarEvent::factory()->create(['title' => 'Etkinlik', 'starts_on' => '2026-11-03']);
        Task::factory()->create(['title' => 'İş', 'due_date' => '2026-11-03']);

        $entries = calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::Task]);

        expect(collect($entries)->pluck('title')->all())->toBe(['İş']);
    });

    it('reads nothing for the alert categories before the site tools exist', function () {
        expect(calendarEntries($this->admin, '2026-11-01', '2026-11-30', [CalendarCategory::AlertDown, CalendarCategory::AlertSoon]))->toBeEmpty();
    });
});

describe('system dates', function () {
    it('lists the end of an active service and sends the click to the customer card', function () {
        $service = CustomerService::factory()->create(['end_date' => '2026-11-03']);
        CustomerService::factory()->cancelled()->create(['end_date' => '2026-11-03']);

        $entries = calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::ServiceEnd]);

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->allDay)->toBeTrue()
            ->and($entries[0]->recorded)->toBeFalse()
            ->and($entries[0]->url)->toBe(route('app.customers.show', $service->customer_id))
            ->and($entries[0]->isEditable())->toBeFalse();
    });

    it('leaves out the services and charges of an archived customer', function () {
        $archived = Customer::factory()->archived()->create();
        CustomerService::factory()->for($archived)->create(['end_date' => '2026-11-03']);
        Charge::factory()->for($archived)->create(['due_date' => '2026-11-03']);

        expect(calendarEntries($this->admin, '2026-11-03', '2026-11-03'))->toBeEmpty();
    });

    it('lists an open charge on its due date with the remaining amount and opens the payment window', function () {
        $charge = Charge::factory()->create(['due_date' => '2026-11-03', 'amount' => 400000]);
        Charge::factory()->paid()->create(['due_date' => '2026-11-03']);

        $entries = calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::ChargeDue]);

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->detail)->toBe('Kalan 4.000 TL')
            ->and($entries[0]->window)->toBe('record-payment')
            ->and($entries[0]->params)->toBe(['charge' => $charge->id]);
    });

    it('lists an open task on its due date, and a finished task only as a record', function () {
        $open = Task::factory()->create(['title' => 'Açık', 'due_date' => '2026-11-03']);
        Task::factory()->completed()->create(['title' => 'Biten', 'due_date' => '2026-11-03', 'completed_at' => '2026-10-28 09:00:00']);

        $dueDay = calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::Task]);
        $doneDay = calendarEntries($this->admin, '2026-10-28', '2026-10-28', [CalendarCategory::Task]);

        expect($dueDay)->toHaveCount(1)
            ->and($dueDay[0]->title)->toBe('Açık')
            ->and($dueDay[0]->window)->toBe('edit-task')
            ->and($dueDay[0]->params)->toBe(['task' => $open->id])
            ->and($doneDay)->toHaveCount(1)
            ->and($doneDay[0]->title)->toBe('İş bitti: Biten')
            ->and($doneDay[0]->recorded)->toBeTrue()
            ->and($doneDay[0]->start)->toBe('09:00');
    });

    it('lists a sent quote on its last valid day and again when it was sent', function () {
        $quote = Quote::factory()->sent()->create(['number' => 'TKL-1', 'valid_until' => '2026-11-03', 'sent_at' => '2026-10-28 09:30:00']);
        Quote::factory()->create(['valid_until' => '2026-11-03']);

        $validDay = calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::Quote]);
        $sentDay = calendarEntries($this->admin, '2026-10-28', '2026-10-28', [CalendarCategory::Quote]);

        expect($validDay)->toHaveCount(1)
            ->and($validDay[0]->title)->toBe('Teklif TKL-1 · Geçerlilik sonu')
            ->and($validDay[0]->recorded)->toBeFalse()
            ->and($validDay[0]->params)->toBe(['quote' => $quote->id])
            ->and($sentDay)->toHaveCount(1)
            ->and($sentDay[0]->title)->toBe('Teklif gönderildi: TKL-1')
            ->and($sentDay[0]->recorded)->toBeTrue()
            ->and($sentDay[0]->start)->toBe('09:30');
    });

    it('names the hand-written recipient of a quote without a customer', function () {
        Quote::factory()->manualRecipient()->sent()->create(['recipient_name' => 'Ali Veli', 'valid_until' => '2026-11-03']);

        expect(calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::Quote])[0]->detail)->toBe('Ali Veli');
    });

    it('lists a payment at the moment it was recorded', function () {
        $payment = Payment::factory()->create(['amount' => 150000]);

        $entries = calendarEntries($this->admin, '2026-10-28', '2026-10-28', [CalendarCategory::Payment]);

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->recorded)->toBeTrue()
            ->and($entries[0]->start)->toBe('10:00')
            ->and($entries[0]->detail)->toBe('1.500 TL')
            ->and($entries[0]->window)->toBe('view-payment')
            ->and($entries[0]->params)->toBe(['payment' => $payment->id]);
    });
});

describe('time zone', function () {
    it('puts a record on the Istanbul day it was made, not the UTC day', function () {
        $this->travelTo('2026-11-03 23:30:00');
        Payment::factory()->create();
        $this->travelTo('2026-11-04 00:10:00');
        Payment::factory()->create();

        $third = calendarEntries($this->admin, '2026-11-03', '2026-11-03', [CalendarCategory::Payment]);
        $fourth = calendarEntries($this->admin, '2026-11-04', '2026-11-04', [CalendarCategory::Payment]);

        expect($third)->toHaveCount(1)
            ->and($third[0]->start)->toBe('23:30')
            ->and($fourth)->toHaveCount(1)
            ->and($fourth[0]->start)->toBe('00:10');
    });

    it('includes the whole last day of the range', function () {
        $this->travelTo('2026-11-08 23:59:00');
        Payment::factory()->create();

        expect(calendarEntries($this->admin, '2026-11-02', '2026-11-08', [CalendarCategory::Payment]))->toHaveCount(1);
    });
});

describe('permissions', function () {
    it('reads no money table for a developer and shows no money entry', function () {
        $developer = User::factory()->developer()->create();
        CalendarEvent::factory()->create(['starts_on' => '2026-11-03']);
        Task::factory()->create(['due_date' => '2026-11-03']);
        CustomerService::factory()->create(['end_date' => '2026-11-03']);
        Charge::factory()->create(['due_date' => '2026-11-03']);
        Payment::factory()->create();
        Quote::factory()->sent()->create(['valid_until' => '2026-11-03']);

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $entries = calendarEntries($developer, '2026-10-01', '2026-11-30');

        $moneyQueries = collect($sql)->filter(fn (string $query): bool => preg_match('/(from|join) `(charges|payments|payment_allocations|quotes|quote_items)`/', $query) === 1);

        expect($moneyQueries)->toBeEmpty()
            ->and(collect($entries)->pluck('category')->unique()->map->value->sort()->values()->all())
            ->toBe(['event', 'service_end', 'task']);
    });

    it('does not read tasks or services for a role without those permissions', function () {
        $role = Role::create(['name' => 'Destek']);
        $user = User::factory()->create();
        $user->assignRole($role);
        Task::factory()->create(['due_date' => '2026-11-03']);
        CustomerService::factory()->create(['end_date' => '2026-11-03']);
        CalendarEvent::factory()->create(['title' => 'Görünür', 'starts_on' => '2026-11-03']);

        $entries = calendarEntries($user, '2026-11-03', '2026-11-03');

        expect(collect($entries)->pluck('title')->all())->toBe(['Görünür']);
    });
});

describe('queries', function () {
    function seedCalendarMonth(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $customer = Customer::factory()->create();
            CalendarEvent::factory()->for($customer)->create(['starts_on' => '2026-11-03']);
            CalendarEvent::factory()->repeating(EventRepeat::Weekly)->create(['starts_on' => '2026-11-04']);
            Task::factory()->for($customer)->create(['due_date' => '2026-11-05']);
            Task::factory()->completed()->for($customer)->create(['completed_at' => '2026-11-05 11:00:00']);
            CustomerService::factory()->for($customer)->forPackage()->create(['end_date' => '2026-11-06']);
            Charge::factory()->for($customer)->create(['due_date' => '2026-11-07']);
            Payment::factory()->for($customer)->create();
            Quote::factory()->sent()->for($customer)->create(['valid_until' => '2026-11-08']);
        }
    }

    function countCalendarQueries(User $user): int
    {
        $user->can(Permission::MoneyView->value);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        app(ListCalendarEntries::class)->handle($user, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));

        return $queries;
    }

    it('runs the same number of queries for a few records and for many', function () {
        seedCalendarMonth(2);
        $small = countCalendarQueries($this->admin);

        seedCalendarMonth(20);
        $large = countCalendarQueries($this->admin);

        expect($small)->toBeGreaterThan(0)
            ->and($large)->toBe($small);
    });
});
