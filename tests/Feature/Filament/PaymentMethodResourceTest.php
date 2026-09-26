<?php

use App\Actions\Payments\SuggestPaymentDefaults;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
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
        ->and(paymentFormMethodNames())->toBe(['Havale/EFT', 'Nakit', 'Kredi Kartı', 'Çek']);
});

it('requires a name', function () {
    Livewire::test(ManagePaymentMethods::class)
        ->callAction('create', ['name' => ''])
        ->assertHasFormErrors(['name' => 'required']);

    expect(PaymentMethod::query()->count())->toBe(3);
});

it('renames a method', function () {
    $cash = paymentMethodNamed('Nakit');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(TestAction::make('edit')->table($cash), ['name' => 'Elden nakit'])
        ->assertHasNoFormErrors();

    expect($cash->refresh()->name)->toBe('Elden nakit')
        ->and($cash->sort_order)->toBe(2);
});

it('reorders the methods by dragging and the payment form follows', function () {
    $transfer = paymentMethodNamed('Havale/EFT');
    $cash = paymentMethodNamed('Nakit');
    $card = paymentMethodNamed('Kredi Kartı');

    Livewire::test(ManagePaymentMethods::class)
        ->call('reorderTable', [$card->id, $transfer->id, $cash->id]);

    expect(paymentFormMethodNames())->toBe(['Kredi Kartı', 'Havale/EFT', 'Nakit'])
        ->and(app(SuggestPaymentDefaults::class)->handle(null)['payment_method_id'])->toBe($card->id);
});

it('archives a method, hides it from the payment form and brings it back', function () {
    $cash = paymentMethodNamed('Nakit');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(TestAction::make('archive')->table($cash))
        ->assertNotified(__('archive.archived'));

    expect($cash->refresh()->isArchived())->toBeTrue()
        ->and(paymentFormMethodNames())->toBe(['Havale/EFT', 'Kredi Kartı']);

    Livewire::test(ManagePaymentMethods::class)
        ->filterTable('archived', true)
        ->callAction(TestAction::make('unarchive')->table($cash));

    expect($cash->refresh()->isArchived())->toBeFalse()
        ->and(paymentFormMethodNames())->toBe(['Havale/EFT', 'Nakit', 'Kredi Kartı']);
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
    PaymentMethod::query()->where('name', '!=', 'Nakit')->get()->each->archive();
    $cash = paymentMethodNamed('Nakit');

    Livewire::test(ManagePaymentMethods::class)
        ->callAction(TestAction::make('archive')->table($cash))
        ->assertNotified('En az bir ödeme yöntemi aktif kalmalı. Önce başka bir yöntem ekleyin veya arşivden çıkarın.');

    expect($cash->refresh()->isArchived())->toBeFalse();
});

it('has no delete action', function () {
    $cash = paymentMethodNamed('Nakit');

    Livewire::test(ManagePaymentMethods::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($cash));
});
