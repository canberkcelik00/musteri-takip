<?php

use App\Actions\Charges\CancelCharge;
use App\Actions\Charges\SplitCharge;
use App\Actions\Charges\UpdateCharge;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Actions\Payments\RecordPayment;
use App\Actions\Quotes\AcceptQuote;
use App\Enums\Currency;
use App\Livewire\Pages\Quotes\Index;
use App\Livewire\Windows\CancelCharge as CancelChargeWindow;
use App\Livewire\Windows\RecordPayment as RecordPaymentWindow;
use App\Livewire\Windows\ViewPayment;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\User;
use Livewire\Livewire;

function paymentBy(User $user, Customer $customer): Payment
{
    return app(RecordPayment::class)->handle($user, $customer, [
        'amount' => 1000,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::factory()->create()->id,
    ]);
}

it('remembers who recorded a payment', function () {
    $user = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);

    $payment = paymentBy($user, Customer::factory()->create());

    expect($payment->fresh()->recorded_by)->toBe($user->id)
        ->and($payment->recordedBy->name)->toBe('Ayşe Yönetici');
});

it('remembers who cancelled, edited and split a charge', function () {
    $canceller = User::factory()->admin()->create();
    $editor = User::factory()->admin()->create();
    $splitter = User::factory()->admin()->create();

    $cancelled = Charge::factory()->create();
    app(CancelCharge::class)->handle($canceller, $cancelled, 'Yanlış girildi');

    $edited = Charge::factory()->create(['amount' => 1000]);
    app(UpdateCharge::class)->handle($editor, $edited, ['amount' => 2000]);

    $split = Charge::factory()->create(['amount' => 1000]);
    app(SplitCharge::class)->handle($splitter, $split, 400, '2027-01-01');

    expect($cancelled->fresh()->cancelled_by)->toBe($canceller->id)
        ->and($edited->fresh()->updated_by)->toBe($editor->id)
        ->and($split->fresh()->updated_by)->toBe($splitter->id)
        ->and($edited->fresh()->cancelled_by)->toBeNull();
});

it('remembers who accepted a quote', function () {
    $user = User::factory()->admin()->create();
    $this->travelTo('2027-03-01');
    $quote = Quote::factory()->sent()->for(Customer::factory()->create())->create(['valid_until' => '2027-03-15']);

    app(AcceptQuote::class)->handle($user, $quote);

    expect($quote->fresh()->accepted_by)->toBe($user->id);
});

it('leaves the user empty on a charge the scheduler creates', function () {
    $this->travelTo('2027-03-01');
    CustomerService::factory()->create(['start_date' => '2026-03-01', 'end_date' => '2027-03-01', 'price' => 100000]);

    app(CreateDueRenewalCharges::class)->handle();

    expect(Charge::query()->count())->toBeGreaterThan(0)
        ->and(Charge::query()->whereNotNull('updated_by')->orWhereNotNull('cancelled_by')->count())->toBe(0);
});

it('records the signed-in user from the windows', function () {
    $user = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($user)->test(RecordPaymentWindow::class, ['customer' => $customer->id])
        ->set('amount', '10,00')
        ->set('paid_on', '2026-03-10')
        ->set('payment_method_id', (string) PaymentMethod::factory()->create()->id)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($user)->test(CancelChargeWindow::class, ['charge' => Charge::factory()->create()->id])
        ->set('reason', 'Hata')
        ->call('save');

    expect(Payment::query()->firstOrFail()->recorded_by)->toBe($user->id)
        ->and(Charge::query()->where('cancelled_by', $user->id)->count())->toBe(1);
});

it('shows who recorded the payment and who accepted the quote', function () {
    $user = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);
    $payment = paymentBy($user, Customer::factory()->create());
    $quote = Quote::factory()->accepted()->for(Customer::factory()->create())->create(['accepted_by' => $user->id]);

    Livewire::actingAs($user)->test(ViewPayment::class, ['payment' => $payment->id])
        ->assertSee(__('payments.recorded_by'))
        ->assertSee('Ayşe Yönetici');

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee(__('quotes.accepted_by', ['name' => 'Ayşe Yönetici']));
    expect($quote->acceptedBy->is($user))->toBeTrue();
});

it('shows no name for a payment from before the users', function () {
    $payment = Payment::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(ViewPayment::class, ['payment' => $payment->id])
        ->assertDontSee(__('payments.recorded_by'));
});
