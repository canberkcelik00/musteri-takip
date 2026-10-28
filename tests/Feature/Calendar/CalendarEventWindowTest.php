<?php

use App\Actions\Calendar\SendCalendarEventInvites;
use App\Enums\EventRepeat;
use App\Enums\Permission;
use App\Exceptions\CalendarMailNotSent;
use App\Livewire\Pages\Calendar\Index;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditCalendarEvent;
use App\Mail\CalendarEventInviteMail;
use App\Models\CalendarEvent;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->me = User::factory()->developer()->create(['name' => 'Ben Kendim']);
    $this->colleague = User::factory()->developer()->create(['name' => 'Is Arkadasi']);
});

it('is registered with the calendar permission and refuses a user without it', function () {
    expect(config('ui.window_permissions.edit-calendar-event'))->toBe(Permission::CalendarManage->value)
        ->and(config('ui.windows.edit-calendar-event'))->toBe(EditCalendarEvent::class);

    $user = User::factory()->developer()->create();
    $user->syncRoles([]);

    Livewire::actingAs($user)->test(EditCalendarEvent::class)->assertForbidden();
});

it('opens on the next full hour today with a one hour length and me as attendee', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-28 09:20:00', 'Europe/Istanbul'));

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class)
        ->assertSet('date', '2026-10-28')
        ->assertSet('startTime', '10:00')
        ->assertSet('endTime', '11:00')
        ->assertSet('attendeeIds', [(string) $this->me->id])
        ->assertSet('repeat', 'none');
});

it('keeps the default on the same day late in the evening', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-28 23:40:00', 'Europe/Istanbul'));

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class)
        ->assertSet('startTime', '23:00')
        ->assertSet('endTime', '');
});

it('opens on the day and hour that were passed and ignores invalid ones', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-28 09:20:00', 'Europe/Istanbul'));

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['date' => '2026-11-03', 'time' => '14:30'])
        ->assertSet('date', '2026-11-03')
        ->assertSet('startTime', '14:30')
        ->assertSet('endTime', '15:30');

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['date' => '2026-02-31', 'time' => '25:00'])
        ->assertSet('date', '2026-10-28')
        ->assertSet('startTime', '10:00');
});

it('opens with the customer filled when a card passes it', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['customer' => $customer->id])
        ->assertSet('customerId', (string) $customer->id);
});

it('sets the end from the duration pills and ignores other values', function () {
    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['date' => '2026-11-03', 'time' => '09:00'])
        ->call('pickDuration', 30)->assertSet('endTime', '09:30')
        ->call('pickDuration', 120)->assertSet('endTime', '11:00')
        ->call('pickDuration', 45)->assertSet('endTime', '11:00')
        ->set('startTime', '23:30')
        ->call('pickDuration', 60)->assertSet('endTime', '11:00');
});

it('creates an event and e-mails the attendees other than me', function () {
    Mail::fake();

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['date' => '2026-11-03', 'time' => '09:00'])
        ->set('title', 'Sunum')
        ->call('toggleAttendee', (string) $this->colleague->id)
        ->set('remindMinutes', '30')
        ->set('location', 'Ofis')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('calendar-changed')
        ->assertDispatched('close-window')
        ->assertDispatched('notify', message: __('calendar.saved'));

    $event = CalendarEvent::query()->firstOrFail();

    expect($event->title)->toBe('Sunum')
        ->and($event->starts_on->format('Y-m-d'))->toBe('2026-11-03')
        ->and($event->start_time)->toBe('09:00:00')
        ->and($event->end_time)->toBe('10:00:00')
        ->and($event->remind_minutes)->toBe(30)
        ->and($event->created_by)->toBe($this->me->id)
        ->and($event->attendees->modelKeys())->toEqualCanonicalizing([$this->me->id, $this->colleague->id]);
    Mail::assertSent(CalendarEventInviteMail::class, 1);
    Mail::assertSent(CalendarEventInviteMail::class, fn (CalendarEventInviteMail $mail): bool => $mail->hasTo($this->colleague->email));
});

it('takes an attendee off when clicked again', function () {
    Livewire::actingAs($this->me)->test(EditCalendarEvent::class)
        ->call('toggleAttendee', (string) $this->me->id)
        ->assertSet('attendeeIds', []);
});

it('saves an all-day event without times', function () {
    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['date' => '2026-11-03'])
        ->set('title', 'Izin')
        ->set('allDay', true)
        ->call('save')
        ->assertHasNoErrors();

    $event = CalendarEvent::query()->firstOrFail();

    expect($event->all_day)->toBeTrue()
        ->and($event->start_time)->toBeNull()
        ->and($event->end_time)->toBeNull();
});

it('shows the Action refusals at the top of the window', function () {
    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['date' => '2026-11-03', 'time' => '09:00'])
        ->set('title', 'Ters')
        ->set('endTime', '08:00')
        ->call('save')
        ->assertHasErrors('form')
        ->assertNotDispatched('close-window');

    expect(CalendarEvent::query()->count())->toBe(0);
});

it('requires a title and a valid date', function () {
    Livewire::actingAs($this->me)->test(EditCalendarEvent::class)
        ->set('title', '')
        ->set('date', 'yarin')
        ->call('save')
        ->assertHasErrors(['title', 'date']);
});

it('keeps the event saved and says so when the invite e-mail fails', function () {
    $this->mock(SendCalendarEventInvites::class)
        ->shouldReceive('handle')->andThrow(new CalendarMailNotSent(['smtp down']));

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class)
        ->set('title', 'Toplanti')
        ->call('toggleAttendee', (string) $this->colleague->id)
        ->call('save')
        ->assertDispatched('calendar-changed')
        ->assertDispatched('close-window')
        ->assertDispatched('notify', tone: 'danger');

    expect(CalendarEvent::query()->count())->toBe(1);
});

it('edits a repeating event for the whole series', function () {
    $event = CalendarEvent::factory()->create([
        'title' => 'Haftalik',
        'starts_on' => '2026-11-02',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'repeat' => EventRepeat::Weekly,
        'created_by' => $this->me->id,
    ]);

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['event' => $event->id])
        ->assertSet('title', 'Haftalik')
        ->assertSet('startTime', '09:00')
        ->assertSet('endTime', '10:00')
        ->assertSet('repeat', 'weekly')
        ->assertSee(__('calendar.form.series_hint'))
        ->set('startTime', '11:00')
        ->set('endTime', '12:00')
        ->set('repeatUntil', '2026-12-01')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect(CalendarEvent::query()->count())->toBe(1)
        ->and($event->start_time)->toBe('11:00:00')
        ->and($event->repeat_until?->format('Y-m-d'))->toBe('2026-12-01');
});

it('drops the end date of a series that stops repeating', function () {
    $event = CalendarEvent::factory()->create([
        'repeat' => EventRepeat::Monthly,
        'repeat_until' => '2027-01-01',
        'starts_on' => '2026-11-02',
        'created_by' => $this->me->id,
    ]);

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['event' => $event->id])
        ->set('repeat', 'none')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->repeat)->toBe(EventRepeat::None)
        ->and($event->repeat_until)->toBeNull();
});

it('lists the odd reminder an event already has', function () {
    $event = CalendarEvent::factory()->create(['remind_minutes' => 45, 'starts_on' => '2026-11-02', 'created_by' => $this->me->id]);

    Livewire::actingAs($this->me)->test(EditCalendarEvent::class, ['event' => $event->id])
        ->assertSet('remindMinutes', '45')
        ->assertSee('45 dakika önce');
});

it('asks for the deletion in the confirm window and deletes the whole series', function () {
    $event = CalendarEvent::factory()->create(['repeat' => EventRepeat::Weekly, 'starts_on' => '2026-11-02', 'created_by' => $this->me->id]);

    Livewire::actingAs($this->me)->test(Confirm::class, ['action' => 'delete-calendar-event', 'id' => $event->id])
        ->assertSee(__('calendar.delete_heading'))
        ->call('confirm')
        ->assertDispatched('calendar-changed')
        ->assertDispatched('close-window');

    expect(CalendarEvent::query()->count())->toBe(0);
});

it('refuses the deletion to a user without the calendar permission', function () {
    $event = CalendarEvent::factory()->create(['starts_on' => '2026-11-02', 'created_by' => $this->me->id]);
    $user = User::factory()->developer()->create();
    $user->syncRoles([]);

    Livewire::actingAs($user)->test(Confirm::class, ['action' => 'delete-calendar-event', 'id' => $event->id])->assertForbidden();

    expect(CalendarEvent::query()->count())->toBe(1);
});

it('offers adding, editing and deleting on the calendar to a user who may manage it', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-03 08:00:00', 'Europe/Istanbul'));
    $event = CalendarEvent::factory()->create(['title' => 'Planlama', 'starts_on' => '2026-11-03', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'created_by' => $this->me->id]);

    Livewire::actingAs($this->me)->test(Index::class, ['view' => 'day', 'date' => '2026-11-03'])
        ->assertSeeHtml("open-window', { name: 'edit-calendar-event', params: { date: '2026-11-03' } }")
        ->call('select', "event:{$event->id}:2026-11-03", '2026-11-03')
        ->assertSeeHtml("name: 'edit-calendar-event', params: { event: {$event->id} }")
        ->assertSeeHtml("name: 'confirm', params: { action: 'delete-calendar-event', id: {$event->id} }");
});

it('hides adding, editing and deleting from a user without the calendar permission', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-03 08:00:00', 'Europe/Istanbul'));
    $event = CalendarEvent::factory()->create(['title' => 'Planlama', 'starts_on' => '2026-11-03', 'start_time' => '10:00:00', 'created_by' => $this->me->id]);
    $user = User::factory()->developer()->create();
    $user->syncRoles([]);

    Livewire::actingAs($user)->test(Index::class, ['view' => 'day', 'date' => '2026-11-03'])
        ->call('select', "event:{$event->id}:2026-11-03", '2026-11-03')
        ->assertSee('Planlama')
        ->assertDontSee('edit-calendar-event')
        ->assertDontSee('delete-calendar-event');
});

it('opens the event window from an empty hour slot only for a user who may manage the calendar', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-03 08:00:00', 'Europe/Istanbul'));
    $user = User::factory()->developer()->create();
    $user->syncRoles([]);

    Livewire::actingAs($this->me)->test(Index::class, ['view' => 'day', 'date' => '2026-11-03'])
        ->assertSeeHtml("name: 'edit-calendar-event', params: { date: '2026-11-03', time: ");

    Livewire::actingAs($user)->test(Index::class, ['view' => 'day', 'date' => '2026-11-03'])
        ->assertDontSeeHtml('edit-calendar-event');
});
