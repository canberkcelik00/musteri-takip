<?php

use App\Actions\Calendar\CreateCalendarEvent;
use App\Actions\Calendar\DeleteCalendarEvent;
use App\Actions\Calendar\UpdateCalendarEvent;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\Users\DeleteUser;
use App\Enums\EventRepeat;
use App\Exceptions\CalendarMailNotSent;
use App\Exceptions\InvalidCalendarEvent;
use App\Exceptions\InvalidUserChange;
use App\Exceptions\RecordInUse;
use App\Mail\CalendarEventInviteMail;
use App\Models\CalendarEvent;
use App\Models\CalendarEventReminder;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

function eventAttributes(array $overrides = []): array
{
    return array_merge([
        'title' => 'Toplantı',
        'starts_on' => '2026-11-03',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ], $overrides);
}

it('creates a timed event for the actor', function () {
    $actor = User::factory()->developer()->create();

    $event = app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['location' => 'Ofis', 'remind_minutes' => 30]));

    expect($event->created_by)->toBe($actor->id)
        ->and($event->starts_on->toDateString())->toBe('2026-11-03')
        ->and($event->start_time)->toBe('10:00:00')
        ->and($event->end_time)->toBe('11:00:00')
        ->and($event->all_day)->toBeFalse()
        ->and($event->repeat)->toBe(EventRepeat::None)
        ->and($event->remind_minutes)->toBe(30)
        ->and($event->location)->toBe('Ofis');
});

it('does not let the end come before or at the start', function (string $end) {
    $actor = User::factory()->developer()->create();

    expect(fn () => app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['end_time' => $end])))
        ->toThrow(InvalidCalendarEvent::class);

    expect(CalendarEvent::query()->count())->toBe(0);
})->with(['before' => '09:00', 'equal' => '10:00']);

it('needs a start time unless the event lasts the whole day', function () {
    $actor = User::factory()->developer()->create();

    expect(fn () => app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['start_time' => null, 'end_time' => null])))
        ->toThrow(InvalidCalendarEvent::class);
});

it('leaves the time fields empty on an all-day event', function () {
    $actor = User::factory()->developer()->create();

    $event = app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['all_day' => true]));

    expect($event->all_day)->toBeTrue()
        ->and($event->start_time)->toBeNull()
        ->and($event->end_time)->toBeNull();
});

it('empties the times when an event becomes all-day', function () {
    $actor = User::factory()->developer()->create();
    $event = CalendarEvent::factory()->create();

    app(UpdateCalendarEvent::class)->handle($actor, $event, eventAttributes(['all_day' => true]));

    expect($event->fresh()->start_time)->toBeNull()
        ->and($event->fresh()->end_time)->toBeNull();
});

it('keeps no end date on an event that does not repeat and refuses one before the start', function () {
    $actor = User::factory()->developer()->create();

    $single = app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['repeat_until' => '2026-12-01']));
    $series = app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['repeat' => 'weekly', 'repeat_until' => '2026-12-01']));

    expect($single->repeat_until)->toBeNull()
        ->and($series->repeat)->toBe(EventRepeat::Weekly)
        ->and($series->repeat_until->toDateString())->toBe('2026-12-01');

    expect(fn () => app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['repeat' => 'monthly', 'repeat_until' => '2026-11-02'])))
        ->toThrow(InvalidCalendarEvent::class);
});

it('refuses an inactive attendee and saves nothing', function () {
    Mail::fake();
    $actor = User::factory()->developer()->create();
    $inactive = User::factory()->developer()->deactivated()->create();

    expect(fn () => app(CreateCalendarEvent::class)->handle($actor, eventAttributes(), [$inactive->id]))
        ->toThrow(InvalidCalendarEvent::class);

    expect(CalendarEvent::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('refuses an archived customer', function () {
    $actor = User::factory()->developer()->create();
    $customer = Customer::factory()->archived()->create();

    expect(fn () => app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['customer_id' => $customer->id])))
        ->toThrow(InvalidCalendarEvent::class);
});

it('lets an event stay with a customer archived since', function () {
    $actor = User::factory()->developer()->create();
    $customer = Customer::factory()->archived()->create();
    $event = CalendarEvent::factory()->create(['customer_id' => $customer->id]);

    app(UpdateCalendarEvent::class)->handle($actor, $event, eventAttributes(['title' => 'Yeni başlık', 'customer_id' => $customer->id]));

    expect($event->fresh()->title)->toBe('Yeni başlık');
});

it('e-mails every attendee except the actor', function () {
    Mail::fake();
    $actor = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);
    $other = User::factory()->developer()->create();

    $event = app(CreateCalendarEvent::class)->handle($actor, eventAttributes(), [$actor->id, $other->id]);

    expect($event->attendees()->count())->toBe(2);
    Mail::assertSent(CalendarEventInviteMail::class, fn (CalendarEventInviteMail $mail) => $mail->hasTo($other->email)
        && $mail->invitedBy->is($actor));
    Mail::assertSentCount(1);
});

it('sends nothing when the actor is the only attendee or there are none', function () {
    Mail::fake();
    $actor = User::factory()->developer()->create();

    app(CreateCalendarEvent::class)->handle($actor, eventAttributes(), [$actor->id]);
    app(CreateCalendarEvent::class)->handle($actor, eventAttributes());

    Mail::assertNothingSent();
});

it('e-mails only the attendees an update adds, and drops the removed ones', function () {
    Mail::fake();
    $actor = User::factory()->admin()->create();
    $first = User::factory()->developer()->create();
    $second = User::factory()->developer()->create();
    $event = CalendarEvent::factory()->create();
    $event->attendees()->attach($first);

    app(UpdateCalendarEvent::class)->handle($actor, $event, eventAttributes(), [$first->id]);
    Mail::assertNothingSent();

    app(UpdateCalendarEvent::class)->handle($actor, $event, eventAttributes(), [$second->id]);

    Mail::assertSent(CalendarEventInviteMail::class, fn (CalendarEventInviteMail $mail) => $mail->hasTo($second->email));
    Mail::assertSentCount(1);
    expect($event->attendees()->pluck('users.id')->all())->toBe([$second->id]);
});

it('lets an attendee deactivated since stay on the event', function () {
    Mail::fake();
    $actor = User::factory()->admin()->create();
    $inactive = User::factory()->developer()->deactivated()->create();
    $event = CalendarEvent::factory()->create();
    $event->attendees()->attach($inactive);

    app(UpdateCalendarEvent::class)->handle($actor, $event, eventAttributes(['title' => 'Aynı']), [$inactive->id]);

    expect($event->fresh()->title)->toBe('Aynı');
});

it('keeps the event saved and raises when an e-mail cannot be sent', function () {
    $actor = User::factory()->admin()->create();
    $first = User::factory()->developer()->create();
    $second = User::factory()->developer()->create();

    Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('SMTP kapalı'));

    expect(fn () => app(CreateCalendarEvent::class)->handle($actor, eventAttributes(['title' => 'Kaydedilir']), [$first->id, $second->id]))
        ->toThrow(CalendarMailNotSent::class, 'SMTP kapalı');

    $event = CalendarEvent::query()->where('title', 'Kaydedilir')->firstOrFail();

    expect($event->attendees()->count())->toBe(2);
});

it('shows the event in the invitation and carries no money', function () {
    $actor = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);
    $attendee = User::factory()->developer()->create();
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    $event = CalendarEvent::factory()->for($customer)->create([
        'title' => 'Sunum',
        'starts_on' => '2026-11-05',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
        'location' => 'Ofis',
        'notes' => 'Projeyi getir',
        'repeat' => EventRepeat::Weekly,
    ]);

    $mail = new CalendarEventInviteMail($event, $attendee, $actor);

    $mail->assertHasSubject('Etkinliğe eklendiniz: Sunum');
    $mail->assertSeeInHtml('05.11.2026 · 14:00–15:30');
    $mail->assertSeeInHtml('Her hafta');
    $mail->assertSeeInHtml('Logi');
    $mail->assertSeeInHtml('Ofis');
    $mail->assertSeeInHtml('Projeyi getir');
    $mail->assertSeeInHtml('Ayşe Yönetici');
    $mail->assertSeeInHtml(url('/calendar'));
    $mail->assertSeeInText('Sunum');
    $mail->assertDontSeeInHtml('₺');
});

it('deletes the whole series with its attendees and sent reminders', function () {
    $event = CalendarEvent::factory()->repeating(EventRepeat::Weekly)->create();
    $event->attendees()->attach(User::factory()->developer()->create());
    CalendarEventReminder::query()->create(['calendar_event_id' => $event->id, 'occurs_on' => '2026-11-03', 'sent_at' => now()]);

    app(DeleteCalendarEvent::class)->handle($event);

    expect(CalendarEvent::query()->count())->toBe(0)
        ->and(CalendarEventReminder::query()->count())->toBe(0)
        ->and(DB::table('calendar_event_user')->count())->toBe(0);
});

it('does not delete a customer who has an event', function () {
    $event = CalendarEvent::factory()->for(Customer::factory())->create();

    expect(fn () => app(DeleteCustomer::class)->handle($event->customer))->toThrow(RecordInUse::class);

    expect(Customer::query()->find($event->customer_id))->not->toBeNull();
});

it('does not delete a user who created or attends an event', function () {
    $actor = User::factory()->admin()->create();
    $creator = User::factory()->developer()->create();
    $attendee = User::factory()->developer()->create();
    $event = CalendarEvent::factory()->create(['created_by' => $creator->id]);
    $event->attendees()->attach($attendee);

    expect(fn () => app(DeleteUser::class)->handle($actor, $creator))->toThrow(InvalidUserChange::class)
        ->and(fn () => app(DeleteUser::class)->handle($actor, $attendee))->toThrow(InvalidUserChange::class);

    expect(User::query()->find($creator->id))->not->toBeNull()
        ->and(User::query()->find($attendee->id))->not->toBeNull();
});
