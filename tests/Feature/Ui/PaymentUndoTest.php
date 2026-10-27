<?php

use App\Actions\Payments\RecordPayment as RecordPaymentAction;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Livewire\PaymentUndo;
use App\Livewire\Windows\RecordPayment;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->me = User::factory()->admin()->create();
    $this->actingAs($this->me);
    $this->travelTo('2026-06-15 10:00:00');
});

it('offers "Geri al" on the notification of a recorded payment', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-01']);

    $window = Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '1.000')
        ->call('save');

    $payment = $customer->payments()->sole();

    $window->assertDispatched(
        'notify',
        action: ['label' => 'Geri al', 'event' => 'undo-payment', 'params' => ['payment' => $payment->id]],
    );
});

it('undoes the payment and reopens the charge', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-01']);
    $payment = app(RecordPaymentAction::class)->handle($this->me, $customer, [
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    Livewire::test(PaymentUndo::class)
        ->call('undo', $payment->id)
        ->assertDispatched('payments-changed')
        ->assertDispatched('notify', message: 'Ödeme geri alındı, 1 borç yeniden açıldı.');

    expect(Payment::find($payment->id))->toBeNull()
        ->and($charge->refresh()->status)->toBe(ChargeStatus::Open);
});

it('says so when the payment can no longer be taken back', function () {
    $payment = Payment::factory()->create(['recorded_by' => $this->me->id]);
    $this->travel(3)->minutes();

    Livewire::test(PaymentUndo::class)
        ->call('undo', $payment->id)
        ->assertNotDispatched('payments-changed')
        ->assertDispatched('notify', message: __('payments.undo_unavailable'), tone: 'danger');

    expect(Payment::find($payment->id))->not->toBeNull();
});

it('says so when the payment is already gone', function () {
    Livewire::test(PaymentUndo::class)
        ->call('undo', 999999)
        ->assertDispatched('notify', message: __('payments.undo_unavailable'), tone: 'danger');
});

it('forbids a user without the money permission', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::MoneyManage->value);
    $developer = User::factory()->developer()->create();
    $payment = Payment::factory()->create(['recorded_by' => $developer->id]);

    Livewire::actingAs($developer)->test(PaymentUndo::class)->call('undo', $payment->id)->assertForbidden();

    expect(Payment::find($payment->id))->not->toBeNull();
});

it('draws the undo listener only for users who manage money', function () {
    $this->get(route('app.today'))->assertSeeLivewire(PaymentUndo::class);

    RoleModel::findByName('developer')->revokePermissionTo(Permission::MoneyManage->value);

    $this->actingAs(User::factory()->developer()->create())
        ->get(route('app.today'))
        ->assertDontSeeLivewire(PaymentUndo::class);
});
