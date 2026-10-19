<?php

use App\Actions\Payments\SuggestPaymentDefaults;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function paymentMethodNamed(string $name): PaymentMethod
{
    return PaymentMethod::query()->where('name', $name)->firstOrFail();
}

/**
 * @return array<int, string>
 */
function paymentFormMethodNames(): array
{
    return PaymentMethod::query()->notArchived()->orderBy('sort_order')->orderBy('id')->pluck('name')->all();
}

function editMethodPanel(PaymentMethod $method): TestAction
{
    return TestAction::make('edit')->table($method);
}

it('lists the payment methods in their order', function () {
    Livewire::test(ManagePaymentMethods::class)
        ->assertOk()
        ->assertCanSeeTableRecords(PaymentMethod::query()->orderBy('sort_order')->get(), inOrder: true)
        ->assertSee('Havale/EFT');
});

it('adds a new method at the end of the list', function () {
    Livewire::test(ManagePaymentMethods::class)
        ->callAction('create', ['name' => 'Çek'])
        ->assertHasNoFormErrors();

    $new = paymentMethodNamed('Çek');

    expect($new->sort_order)->toBe(4)
        ->and(paymentFormMethodNames())->toBe(['Havale/EFT', 'Kredi Kartı', 'Çek']);
});

it('requires a name', function () {
    Livewire::test(ManagePaymentMethods::class)
        ->callAction('create', ['name' => ''])
        ->assertHasFormErrors(['name' => 'required']);

    expect(PaymentMethod::query()->count())->toBe(2);
});

it('renames a method', function () {
    $card = paymentMethodNamed('Kredi Kartı');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(TestAction::make('edit')->table($card), ['name' => 'Sanal POS'])
        ->assertHasNoFormErrors();

    expect($card->refresh()->name)->toBe('Sanal POS')
        ->and($card->sort_order)->toBe(3);
});

it('reorders the methods by dragging and the payment form follows', function () {
    $transfer = paymentMethodNamed('Havale/EFT');
    $card = paymentMethodNamed('Kredi Kartı');

    Livewire::test(ManagePaymentMethods::class)
        ->call('reorderTable', [$card->id, $transfer->id]);

    expect(paymentFormMethodNames())->toBe(['Kredi Kartı', 'Havale/EFT'])
        ->and(app(SuggestPaymentDefaults::class)->handle(null)['payment_method_id'])->toBe($card->id);
});

it('archives a method, hides it from the payment form and brings it back', function () {
    $card = paymentMethodNamed('Kredi Kartı');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(sectionAction(editMethodPanel($card), 'archive'))
        ->assertNotified(__('archive.archived'));

    expect($card->refresh()->isArchived())->toBeTrue()
        ->and(paymentFormMethodNames())->toBe(['Havale/EFT']);

    Livewire::test(ManagePaymentMethods::class)
        ->filterTable('archived', true)
        ->callAction(sectionAction(editMethodPanel($card), 'unarchive'));

    expect($card->refresh()->isArchived())->toBeFalse()
        ->and(paymentFormMethodNames())->toBe(['Havale/EFT', 'Kredi Kartı']);
});

it('hides archived methods from the list by default and shows them with the archive filter', function () {
    $active = paymentMethodNamed('Havale/EFT');
    $archived = PaymentMethod::factory()->archived()->create();

    Livewire::test(ManagePaymentMethods::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived])
        ->filterTable('archived', true)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active]);
});

it('does not archive the last active method and says why', function () {
    PaymentMethod::query()->where('name', '!=', 'Kredi Kartı')->get()->each->archive();
    $card = paymentMethodNamed('Kredi Kartı');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(sectionAction(editMethodPanel($card), 'archive'))
        ->assertNotified('En az bir ödeme yöntemi aktif kalmalı. Önce başka bir yöntem ekleyin veya arşivden çıkarın.');

    expect($card->refresh()->isArchived())->toBeFalse();
});

it('offers delete next to archive for an unused method and deletes it', function () {
    $method = PaymentMethod::factory()->create();

    $list = Livewire::test(ManagePaymentMethods::class);

    expect(actionOffered($list, sectionAction(editMethodPanel($method), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editMethodPanel($method), 'archive')))->toBeTrue();

    $list->callAction(sectionAction(editMethodPanel($method), 'delete', danger: true));

    expect(PaymentMethod::query()->find($method->id))->toBeNull();
});

it('offers only archive for a method a payment uses', function () {
    $method = Payment::factory()->create()->paymentMethod;

    $list = Livewire::test(ManagePaymentMethods::class);

    expect(actionOffered($list, sectionAction(editMethodPanel($method), 'delete', danger: true)))->toBeFalse()
        ->and(actionOffered($list, sectionAction(editMethodPanel($method), 'archive')))->toBeTrue();
});

it('does not delete the last active method and says why', function () {
    PaymentMethod::query()->where('name', '!=', 'Kredi Kartı')->get()->each->archive();
    $card = paymentMethodNamed('Kredi Kartı');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(sectionAction(editMethodPanel($card), 'delete', danger: true))
        ->assertNotified('En az bir ödeme yöntemi aktif kalmalı. Önce başka bir yöntem ekleyin veya arşivden çıkarın.');

    expect(PaymentMethod::query()->find($card->id))->not->toBeNull();
});

it('offers delete for an archived method that no payment uses', function () {
    $method = PaymentMethod::factory()->create();
    $method->archive();

    $list = Livewire::test(ManagePaymentMethods::class)->filterTable('archived', true);

    expect(actionOffered($list, sectionAction(editMethodPanel($method), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editMethodPanel($method), 'unarchive')))->toBeTrue();
});
