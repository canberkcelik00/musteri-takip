<?php

use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Support\RecordActionsSection;
use App\Mail\RenewalReminderMail;
use App\Models\Charge;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    Mail::fake();
    $this->travelTo('2027-02-10 10:00:00');
});

function reminderCard(Charge $charge)
{
    return Livewire::test(ChargesRelationManager::class, [
        'ownerRecord' => $charge->customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

/**
 * @return list<TestAction>
 */
function reminderPanelAction(Charge $charge): array
{
    return [
        TestAction::make('editCharge')->table($charge),
        TestAction::make('sendRenewalReminder')->schemaComponent(RecordActionsSection::ACTIONS_KEY),
    ];
}

it('shows the date of the last reminder under the charge on the customer card', function () {
    $charge = renewalChargeToRemind(['reminder_sent_at' => '2027-02-01 09:00:00']);

    reminderCard($charge)->assertSee('Hatırlatma gönderildi: 01.02.2027');
});

it('shows no reminder line for a charge that was not reminded', function () {
    $charge = renewalChargeToRemind();

    reminderCard($charge)->assertDontSee('Hatırlatma gönderildi');
});

it('sends the reminder from the edit panel and shows the new date', function () {
    $charge = renewalChargeToRemind(['reminder_sent_at' => '2027-02-01 09:00:00']);

    $card = reminderCard($charge)
        ->mountAction(reminderPanelAction($charge))
        ->callMountedAction()
        ->assertNotified('Hatırlatma gönderildi');

    Mail::assertSent(RenewalReminderMail::class, 1);
    Mail::assertSent(RenewalReminderMail::class, fn (RenewalReminderMail $mail): bool => $mail->hasTo('ayse@example.com'));
    expect($charge->fresh()->reminder_sent_at?->toDateTimeString())->toBe('2027-02-10 10:00:00');

    $card->assertSee('Hatırlatma gönderildi: 10.02.2027');
});

it('names the recipient and the last send in the confirmation', function () {
    $charge = renewalChargeToRemind(['reminder_sent_at' => '2027-02-01 09:00:00']);

    $action = reminderCard($charge)->mountAction(reminderPanelAction($charge))->instance()->getMountedAction();

    expect($action->isConfirmationRequired())->toBeTrue()
        ->and($action->getModalDescription())->toContain('ayse@example.com')
        ->and($action->getModalDescription())->toContain('Son gönderim: 01.02.2027 09:00');
});

it('offers the action for an overdue renewal charge', function () {
    $overdue = renewalChargeToRemind(['due_date' => '2027-02-01']);

    expect(actionOffered(reminderCard($overdue), reminderPanelAction($overdue)))->toBeTrue();
});

it('does not offer the action for a charge that cannot be reminded', function (Closure $make) {
    $charge = $make();

    expect(actionOffered(reminderCard($charge), reminderPanelAction($charge)))->toBeFalse();
})->with([
    'manual charge' => [fn () => Charge::factory()->create(['due_date' => '2027-03-01'])],
    'free service' => [fn () => renewalChargeToRemind(service: ['is_free' => true])],
]);

it('explains and sends nothing when the primary contact has no valid address', function () {
    $charge = renewalChargeToRemind(contact: ['email' => null]);

    $card = reminderCard($charge)->mountAction(reminderPanelAction($charge));
    $action = $card->instance()->getMountedAction();

    expect($action->getModalDescription())->toContain('Birincil kişinin geçerli e-postası yok.');

    $card->callMountedAction()->assertNotified('Birincil kişinin geçerli e-postası yok.');

    Mail::assertNothingSent();
    expect($charge->fresh()->reminder_sent_at)->toBeNull();
});

it('reports a failed send without details and keeps the record unchanged', function () {
    Log::spy();
    $charge = renewalChargeToRemind(['reminder_sent_at' => '2027-02-01 09:00:00']);
    Mail::shouldReceive('to')->once()->andReturn(new class
    {
        public function send(mixed $mailable): void
        {
            throw new TransportException('SMTP şifresi hatalı: gizli-bilgi');
        }
    });

    reminderCard($charge)
        ->mountAction(reminderPanelAction($charge))
        ->callMountedAction()
        ->assertNotified('Hatırlatma gönderilemedi');

    expect($charge->fresh()->reminder_sent_at?->toDateTimeString())->toBe('2027-02-01 09:00:00');
    Log::shouldHaveReceived('error')->once();
});
