<?php

use App\Enums\CalendarCategory;
use App\Enums\EventRepeat;
use App\Livewire\Pages\Calendar\Index as CalendarIndex;
use App\Models\CalendarEvent;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Support\Calendar\CalendarEntry;
use App\Support\Calendar\DayLayout;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    // A Tuesday; its week runs 26 Oct – 1 Nov 2026 (week 44).
    $this->travelTo('2026-10-27 14:20:00');
    $this->admin = User::factory()->admin()->create(['name' => 'Canberk Çelik']);
    $this->developer = User::factory()->developer()->create();
});

function calendarPage(User $user, array $query = [])
{
    return Livewire::actingAs($user)->withQueryParams($query)->test(CalendarIndex::class);
}

describe('the page', function () {
    it('opens for everyone who is signed in and shows the brown menu square', function () {
        foreach ([$this->admin, $this->developer] as $user) {
            $this->actingAs($user)->get(route('app.calendar'))
                ->assertOk()
                ->assertSee(__('calendar.title'))
                ->assertSee('ui-nav-brown', false);
        }
    });

    it('asks a guest to sign in', function () {
        $this->get(route('app.calendar'))->assertRedirect(route('app.login'));
    });

    it('starts on the current week', function () {
        calendarPage($this->admin)
            ->assertSet('view', 'week')
            ->assertSee('26 Ekim – 1 Kasım 2026')
            ->assertSee(__('calendar.week_number', ['number' => 44]));
    });

    it('reads the view, the date and the filters from the address', function () {
        calendarPage($this->admin, ['view' => 'month', 'date' => '2026-11-10', 'hidden' => ['payment'], 'mine' => true])
            ->assertSet('view', 'month')
            ->assertSet('date', '2026-11-10')
            ->assertSet('hidden', ['payment'])
            ->assertSet('mine', true)
            ->assertSee('Kasım 2026');
    });

    it('falls back to today and to the week when the address holds nonsense', function () {
        calendarPage($this->admin, ['view' => 'year', 'date' => '2026-02-31'])
            ->assertSee('26 Ekim – 1 Kasım 2026');
    });
});

describe('moving', function () {
    it('steps a week, a day or a month depending on the view', function () {
        calendarPage($this->admin)->call('next')->assertSet('date', '2026-11-03')->call('previous')->call('previous')->assertSet('date', '2026-10-20');
        calendarPage($this->admin, ['view' => 'day'])->call('next')->assertSet('date', '2026-10-28')->call('previous')->call('previous')->assertSet('date', '2026-10-26');
        calendarPage($this->admin, ['view' => 'month'])->call('next')->assertSet('date', '2026-11-01')->call('previous')->call('previous')->assertSet('date', '2026-09-01');
    });

    it('keeps the date out of the address once it is today again', function () {
        calendarPage($this->admin)->call('next')->assertSet('date', '2026-11-03')->call('goToday')->assertSet('date', '');
    });

    it('opens a day from the month and from the week header', function () {
        calendarPage($this->admin, ['view' => 'month'])->call('openDay', '2026-11-05')->assertSet('view', 'day')->assertSet('date', '2026-11-05');
        calendarPage($this->admin)->call('openDay', 'not-a-date')->assertSet('view', 'week');
    });

    it('moves the small calendar of the day view without moving the day', function () {
        calendarPage($this->admin, ['view' => 'day'])
            ->assertSee('Ekim 2026')
            ->call('stepMiniMonth', 1)
            ->assertSee('Kasım 2026')
            ->assertSet('date', '');
    });
});

describe('what a range shows', function () {
    it('draws a week from Monday to Sunday', function () {
        CalendarEvent::factory()->create(['title' => 'Pazartesi', 'starts_on' => '2026-10-26']);
        CalendarEvent::factory()->create(['title' => 'Hafta sonu kaydı', 'starts_on' => '2026-11-01']);
        CalendarEvent::factory()->create(['title' => 'Eski kayıt', 'starts_on' => '2026-10-25']);
        CalendarEvent::factory()->create(['title' => 'Yeni kayıt', 'starts_on' => '2026-11-02']);

        calendarPage($this->admin)->assertSee('Pazartesi')->assertSee('Hafta sonu kaydı')->assertDontSee('Eski kayıt')->assertDontSee('Yeni kayıt');
    });

    it('draws the whole weeks around a month', function () {
        CalendarEvent::factory()->create(['title' => 'Önceki aydan', 'starts_on' => '2026-10-28']);
        CalendarEvent::factory()->create(['title' => 'Sonraki aydan', 'starts_on' => '2026-12-02']);
        CalendarEvent::factory()->create(['title' => 'Uzakta', 'starts_on' => '2026-12-20']);

        // November 2026 runs Sunday 1 Nov; its first row starts Monday 26 Oct and its last row ends Sunday 6 Dec.
        calendarPage($this->admin, ['view' => 'month', 'date' => '2026-11-10'])
            ->assertSee('Önceki aydan')->assertSee('Sonraki aydan')->assertDontSee('Uzakta');
    });

    it('lists three lines a day in the month and counts the rest', function () {
        foreach (range(1, 5) as $number) {
            CalendarEvent::factory()->create(['title' => "Toplantı {$number}", 'starts_on' => '2026-10-27', 'start_time' => sprintf('%02d:00:00', 8 + $number), 'end_time' => sprintf('%02d:30:00', 8 + $number)]);
        }

        calendarPage($this->admin, ['view' => 'month'])
            ->assertSee('Toplantı 1')->assertSee('Toplantı 3')->assertDontSee('Toplantı 4')
            ->assertSee(__('calendar.more', ['count' => 2]));
    });

    it('lists the days that have entries, today first with its name', function () {
        CalendarEvent::factory()->create(['title' => 'Bugünkü', 'starts_on' => '2026-10-27']);
        CalendarEvent::factory()->create(['title' => 'Yarınki', 'starts_on' => '2026-10-28']);

        calendarPage($this->admin, ['view' => 'list'])
            ->assertSee('Bugün · Salı 27 Ekim')->assertSee('Yarın · Çarşamba 28 Ekim')
            ->assertSee('Bugünkü')->assertSee('Yarınki');
    });

    it('says so when a range is empty', function () {
        calendarPage($this->admin, ['view' => 'list'])->assertSee(__('calendar.nothing'));
    });

    it('shows the system dates beside the events', function () {
        $customer = Customer::factory()->create(['short_name' => 'Mavi Dental']);
        Charge::factory()->for($customer)->create(['due_date' => '2026-10-27']);
        Task::factory()->create(['title' => 'Menü sayfası', 'due_date' => '2026-10-28']);
        CustomerService::factory()->for($customer)->forPackage()->create(['end_date' => '2026-10-29']);

        calendarPage($this->admin)
            ->assertSee(__('calendar.entry.charge_due', ['customer' => 'Mavi Dental']))
            ->assertSee('Menü sayfası')
            ->assertSee(route('app.customers.show', $customer), false);
    });

    it('shows what was recorded on the day in the day view', function () {
        $payment = Payment::factory()->create();

        calendarPage($this->admin, ['view' => 'day'])
            ->assertSee(__('calendar.records_today'))
            ->assertSee(__('calendar.entry.payment', ['customer' => $payment->customer->short_name]));
    });
});

describe('opening an entry', function () {
    it('opens the entry\'s own window for a system record', function () {
        $task = Task::factory()->create(['title' => 'Menü sayfası', 'due_date' => '2026-10-27']);
        $payment = Payment::factory()->create();

        calendarPage($this->admin)
            ->assertSeeHtml("open-window', { name: 'edit-task', params: { task: {$task->id} } }")
            ->assertSeeHtml("open-window', { name: 'view-payment', params: { payment: {$payment->id} } }");
    });

    it('shows an event\'s detail in the day view when it is clicked', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        $event = CalendarEvent::factory()->for($customer)->create([
            'title' => 'Yenileme görüşmesi', 'starts_on' => '2026-10-29', 'start_time' => '15:00:00', 'end_time' => '16:00:00',
            'location' => 'Google Meet', 'notes' => 'Paket yükseltmesini konuş.', 'remind_minutes' => 30,
        ]);
        $event->attendees()->attach($this->admin);

        calendarPage($this->admin)
            ->call('select', "event:{$event->id}:2026-10-29", '2026-10-29')
            ->assertSet('view', 'day')
            ->assertSet('date', '2026-10-29')
            ->assertSee('29.10.2026 · 15:00 – 16:00')
            ->assertSee('Google Meet')
            ->assertSee('Canberk Çelik')
            ->assertSee('30 dakika önce')
            ->assertSee('Paket yükseltmesini konuş.')
            ->assertSee(__('calendar.detail.customer_card'))
            ->call('closeDetail')
            ->assertDontSee('Paket yükseltmesini konuş.');
    });

    it('shows the occurrence that was clicked in a repeating series', function () {
        $event = CalendarEvent::factory()->repeating(EventRepeat::Weekly)->create(['title' => 'Ekip planı', 'starts_on' => '2026-10-20', 'repeat_until' => '2026-11-30']);

        calendarPage($this->admin)
            ->call('select', "event:{$event->id}:2026-10-27", '2026-10-27')
            ->assertSee('27.10.2026 · 10:00 – 11:00')
            ->assertSee(EventRepeat::Weekly->getLabel().' · '.__('calendar.detail.repeat_until', ['until' => '30.11.2026']));
    });

    it('ignores a key that is not an event and a day that is not a date', function () {
        calendarPage($this->admin)
            ->call('select', 'task:1', '2026-10-29')->assertSet('view', 'week')
            ->call('select', 'event:1:2026-10-29', 'nonsense')->assertSet('view', 'week')
            ->call('select', 'event:999:2026-10-29', '2026-10-29')->assertSet('view', 'day')->assertDontSeeHtml('class="ui-pane cal-detail"');
    });
});

describe('filters', function () {
    it('hides a category when its chip is switched off and brings it back', function () {
        CalendarEvent::factory()->create(['title' => 'Ortak toplantı', 'starts_on' => '2026-10-27']);
        Task::factory()->create(['title' => 'Görev', 'due_date' => '2026-10-27']);

        calendarPage($this->admin)
            ->call('toggleChip', 'event')
            ->assertSet('hidden', ['event'])
            ->assertDontSee('Ortak toplantı')->assertSee('Görev')
            ->call('toggleChip', 'event')
            ->assertSet('hidden', [])
            ->assertSee('Ortak toplantı');
    });

    it('ignores a chip that does not exist', function () {
        calendarPage($this->admin)->call('toggleChip', 'bogus')->assertSet('hidden', []);
    });

    it('keeps only my events and tasks with "Yalnız benimkiler"', function () {
        CalendarEvent::factory()->create(['title' => 'Benim etkinliğim', 'starts_on' => '2026-10-27', 'created_by' => $this->admin->id]);
        CalendarEvent::factory()->create(['title' => 'Başkasının etkinliği', 'starts_on' => '2026-10-27', 'created_by' => $this->developer->id]);

        calendarPage($this->admin)->assertSee('Başkasının etkinliği')
            ->call('toggleMine')->assertSet('mine', true)
            ->assertSee('Benim etkinliğim')->assertDontSee('Başkasının etkinliği');
    });

    it('offers a developer only the chips they can use', function () {
        $html = calendarPage($this->developer)->html();

        expect($html)->toContain(__('enums.calendar_category.event'))
            ->toContain(__('enums.calendar_category.task'))
            ->not->toContain(__('enums.calendar_category.charge_due'))
            ->not->toContain(__('enums.calendar_category.payment'))
            ->not->toContain(__('enums.calendar_category.quote'));
    });

    it('shows a developer no amounts, due charges or payments', function () {
        $customer = Customer::factory()->create(['short_name' => 'Para Müşterisi']);
        Charge::factory()->for($customer)->create(['due_date' => '2026-10-27']);
        Payment::factory()->for($customer)->create();
        CalendarEvent::factory()->create(['title' => 'Herkese açık', 'starts_on' => '2026-10-27']);

        calendarPage($this->developer)->assertSee('Herkese açık')->assertDontSee('Para Müşterisi');
    });
});

describe('accessibility', function () {
    it('labels the arrow buttons and the views, and marks pressed chips', function () {
        $html = calendarPage($this->admin)->html();

        expect($html)->toContain('aria-label="'.__('calendar.previous.week').'"')
            ->toContain('aria-label="'.__('calendar.next.week').'"')
            ->toContain('aria-label="'.__('calendar.view_label').'"')
            ->toContain('aria-pressed="true"');
    });
});

describe('the hour layout', function () {
    function timedEntry(string $key, string $start, ?string $end = null): CalendarEntry
    {
        return new CalendarEntry(
            key: $key,
            category: CalendarCategory::Event,
            title: $key,
            date: today(),
            allDay: false,
            start: $start,
            end: $end,
        );
    }

    it('gives entries that do not overlap the whole width', function () {
        $placed = DayLayout::place([timedEntry('a', '09:00', '10:00'), timedEntry('b', '10:00', '11:00')]);

        expect(array_column($placed, 'lane'))->toBe([0, 0])->and(array_column($placed, 'lanes'))->toBe([1, 1]);
    });

    it('puts overlapping entries side by side', function () {
        $placed = DayLayout::place([timedEntry('a', '09:00', '11:00'), timedEntry('b', '10:00', '12:00'), timedEntry('c', '13:00', '14:00')]);

        expect(array_column($placed, 'lane'))->toBe([0, 1, 0])->and(array_column($placed, 'lanes'))->toBe([2, 2, 1]);
    });

    it('draws an entry without an end for half an hour and never shorter', function () {
        [$open, $short] = DayLayout::place([timedEntry('a', '09:00'), timedEntry('b', '12:00', '12:10')]);

        expect($open['to'] - $open['from'])->toBe(30)->and($short['to'] - $short['from'])->toBe(30);
    });

    it('leaves all-day entries to the all-day row', function () {
        $allDay = new CalendarEntry('x', CalendarCategory::Task, 'x', today(), true);

        expect(DayLayout::place([$allDay]))->toBe([]);
    });
});

describe('queries', function () {
    it('does not run more queries for a busy week than for a quiet one', function () {
        $count = function (): int {
            $this->admin->can('money.view');
            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });
            calendarPage($this->admin)->html();

            return $queries;
        };

        $seed = function (int $rounds): void {
            foreach (range(1, $rounds) as $unused) {
                $customer = Customer::factory()->create();
                CalendarEvent::factory()->for($customer)->create(['starts_on' => '2026-10-28']);
                Task::factory()->for($customer)->create(['due_date' => '2026-10-29']);
                Charge::factory()->for($customer)->create(['due_date' => '2026-10-30']);
                Payment::factory()->for($customer)->create();
            }
        };

        // One round first: an empty result skips its eager loads, which would make the quiet week cheaper.
        $seed(1);
        $small = $count();
        $seed(6);

        expect($count())->toBe($small);
    });
});
