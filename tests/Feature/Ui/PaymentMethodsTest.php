<?php

use App\Livewire\Pages\Settings\PaymentMethods;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditPaymentMethod;
use App\Models\PaymentMethod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

/**
 * Three active methods in a fixed order; the methods the migration seeds are removed first.
 *
 * @return array{PaymentMethod, PaymentMethod, PaymentMethod}
 */
function uiMethods(): array
{
    PaymentMethod::query()->delete();

    return [
        PaymentMethod::factory()->create(['name' => 'Nakit', 'sort_order' => 10]),
        PaymentMethod::factory()->create(['name' => 'Havale', 'sort_order' => 20]),
        PaymentMethod::factory()->create(['name' => 'Kredi Kartı', 'sort_order' => 30]),
    ];
}

it('lists the methods in their order and hides archived ones', function () {
    [$cash] = uiMethods();
    PaymentMethod::factory()->archived()->create(['name' => 'Çek', 'sort_order' => 40]);

    Livewire::test(PaymentMethods::class)
        ->assertSeeInOrder(['Nakit', 'Havale', 'Kredi Kartı'])
        ->assertDontSee('Çek')
        ->set('archive', PaymentMethods::ARCHIVE_ALL)->assertSee('Çek');

    expect($cash->exists)->toBeTrue();
});

it('writes a dragged order into the slots the rows had', function () {
    [$cash, $transfer, $card] = uiMethods();

    Livewire::test(PaymentMethods::class)->call('reorder', [$card->id, $cash->id, $transfer->id]);

    expect(PaymentMethod::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Kredi Kartı', 'Nakit', 'Havale']);
});

it('keeps the slots of hidden archived rows when reordering', function () {
    [$cash, $transfer] = uiMethods();
    $archived = PaymentMethod::factory()->archived()->create(['name' => 'Çek', 'sort_order' => 15]);

    Livewire::test(PaymentMethods::class)->call('reorder', [$transfer->id, $cash->id]);

    expect($archived->fresh()->sort_order)->toBe(15)
        ->and($transfer->fresh()->sort_order)->toBe(10)
        ->and($cash->fresh()->sort_order)->toBe(20);
});

it('moves a method up and down by one place and stops at the ends', function () {
    [$cash, $transfer, $card] = uiMethods();

    $order = fn (): array => PaymentMethod::query()->orderBy('sort_order')->pluck('name')->all();
    $page = Livewire::test(PaymentMethods::class);

    $page->call('move', $card->id, -1);
    expect($order())->toBe(['Nakit', 'Kredi Kartı', 'Havale']);

    $page->call('move', $cash->id, -1);
    expect($order())->toBe(['Nakit', 'Kredi Kartı', 'Havale']);

    $page->call('move', $card->id, 1);
    expect($order())->toBe(['Nakit', 'Havale', 'Kredi Kartı']);
});

it('creates and renames a method', function () {
    Livewire::test(EditPaymentMethod::class)
        ->set('name', 'Mobil Ödeme')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('payment-methods-changed')
        ->assertDispatched('close-window');

    $method = PaymentMethod::query()->where('name', 'Mobil Ödeme')->firstOrFail();

    Livewire::test(EditPaymentMethod::class, ['method' => $method->id])
        ->assertSet('name', 'Mobil Ödeme')
        ->set('name', 'Mobil')
        ->call('save')
        ->assertHasNoErrors();

    expect($method->fresh()->name)->toBe('Mobil');
});

it('rejects an empty name', function () {
    Livewire::test(EditPaymentMethod::class)->set('name', '')->call('save')->assertHasErrors('name');
});

it('refuses to archive the last active method', function () {
    PaymentMethod::query()->delete();
    $only = PaymentMethod::factory()->create();

    Livewire::test(Confirm::class, ['action' => 'archive-payment-method', 'id' => $only->id])
        ->call('confirm')
        ->assertHasErrors('form');

    expect($only->fresh()->isArchived())->toBeFalse();
});

it('archives, restores and deletes a method through the confirm window', function () {
    [$cash, $transfer] = uiMethods();

    Livewire::test(Confirm::class, ['action' => 'archive-payment-method', 'id' => $cash->id])->call('confirm');
    expect($cash->fresh()->isArchived())->toBeTrue();

    Livewire::test(Confirm::class, ['action' => 'unarchive-payment-method', 'id' => $cash->id])->call('confirm');
    expect($cash->fresh()->isArchived())->toBeFalse();

    Livewire::test(Confirm::class, ['action' => 'delete-payment-method', 'id' => $transfer->id])->call('confirm');
    expect(PaymentMethod::query()->find($transfer->id))->toBeNull();
});

it('needs a signed-in user for the payment methods page', function () {
    auth()->logout();

    $this->get(route('app.payment-methods'))->assertRedirect();
});
