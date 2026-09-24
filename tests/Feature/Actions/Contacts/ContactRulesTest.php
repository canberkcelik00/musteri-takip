<?php

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\MakeContactPrimary;
use App\Exceptions\PrimaryContactCannotBeDeleted;
use App\Models\Contact;
use App\Models\Customer;

/**
 * @return array<int, int>
 */
function primaryContactIds(Customer $customer): array
{
    return $customer->contacts()->where('is_primary', true)->pluck('id')->all();
}

it('makes the first contact primary automatically', function () {
    $customer = Customer::factory()->create();

    $contact = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);

    expect($contact->is_primary)->toBeTrue()
        ->and(primaryContactIds($customer))->toBe([$contact->id]);
});

it('does not make a later contact primary by default', function () {
    $customer = Customer::factory()->create();
    $first = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);

    $second = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);

    expect($second->fresh()->is_primary)->toBeFalse()
        ->and(primaryContactIds($customer))->toBe([$first->id]);
});

it('moves the primary flag to a new contact added as primary', function () {
    $customer = Customer::factory()->create();
    $first = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);

    $second = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya'], makePrimary: true);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and(primaryContactIds($customer))->toBe([$second->id]);
});

it('removes the previous primary when another contact is made primary', function () {
    $customer = Customer::factory()->create();
    $first = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);
    $second = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);

    app(MakeContactPrimary::class)->handle($second);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue()
        ->and(primaryContactIds($customer))->toBe([$second->id]);
});

it('does not touch the primary contact of another customer', function () {
    $otherCustomer = Customer::factory()->create();
    $otherPrimary = app(CreateContact::class)->handle($otherCustomer, ['name' => 'Zeynep Demir']);

    $customer = Customer::factory()->create();
    app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);
    $second = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);
    app(MakeContactPrimary::class)->handle($second);

    expect($otherPrimary->fresh()->is_primary)->toBeTrue()
        ->and(primaryContactIds($otherCustomer))->toBe([$otherPrimary->id]);
});

it('does not delete the primary contact', function () {
    $customer = Customer::factory()->create();
    $primary = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);
    app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);

    expect(fn () => app(DeleteContact::class)->handle($primary))
        ->toThrow(PrimaryContactCannotBeDeleted::class, 'Birincil kişi silinemez; önce başka bir kişiyi birincil yapın.');

    expect($primary->fresh())->not->toBeNull()
        ->and(primaryContactIds($customer))->toBe([$primary->id]);
});

it('deletes a contact that is not primary', function () {
    $customer = Customer::factory()->create();
    $primary = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);
    $other = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);

    app(DeleteContact::class)->handle($other);

    expect(Contact::find($other->id))->toBeNull()
        ->and(primaryContactIds($customer))->toBe([$primary->id]);
});

it('deletes the former primary after another contact is made primary', function () {
    $customer = Customer::factory()->create();
    $first = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);
    $second = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);

    app(MakeContactPrimary::class)->handle($second);
    app(DeleteContact::class)->handle($first->fresh());

    expect(primaryContactIds($customer))->toBe([$second->id]);
});

it('does not let mass assignment change the primary flag', function () {
    $customer = Customer::factory()->create();
    $primary = app(CreateContact::class)->handle($customer, ['name' => 'Ayşe Yılmaz']);
    $other = app(CreateContact::class)->handle($customer, ['name' => 'Mehmet Kaya']);

    $primary->update(['is_primary' => false]);
    $other->update(['is_primary' => true]);

    expect(primaryContactIds($customer))->toBe([$primary->id]);
});
