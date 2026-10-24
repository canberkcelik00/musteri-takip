<?php

use App\Livewire\Windows\CreateCustomer;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates the customer with its first contact as the primary one and opens the card', function () {
    Livewire::test(CreateCustomer::class)
        ->set('short_name', 'Logi')
        ->set('legal_title', 'Logitech Ltd. Şti.')
        ->set('contact_name', 'Ayşe Yılmaz')
        ->set('contact_email', 'ayse@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('customers-changed')
        ->assertDispatched('notify', message: __('customers.created'));

    $customer = Customer::query()->where('short_name', 'Logi')->sole();
    $contact = $customer->contacts()->sole();

    expect($customer->legal_title)->toBe('Logitech Ltd. Şti.')
        ->and($customer->tax_office)->toBeNull()
        ->and($contact->name)->toBe('Ayşe Yılmaz')
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->email)->toBe('ayse@example.com');
});

it('redirects to the customer card after saving', function () {
    $component = Livewire::test(CreateCustomer::class)
        ->set('short_name', 'Logi')
        ->set('contact_name', 'Ayşe')
        ->call('save');

    $id = Customer::query()->where('short_name', 'Logi')->value('id');

    $component->assertRedirect(route('app.customers.show', $id));
});

it('asks for a short name and a contact name', function () {
    Livewire::test(CreateCustomer::class)
        ->call('save')
        ->assertHasErrors(['short_name' => 'required', 'contact_name' => 'required']);

    expect(Customer::query()->count())->toBe(0);
});

it('refuses a short name that is already taken and a bad e-mail address', function () {
    Customer::factory()->create(['short_name' => 'Logi']);

    Livewire::test(CreateCustomer::class)
        ->set('short_name', 'Logi')
        ->set('contact_name', 'Ayşe')
        ->set('contact_email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['short_name' => 'unique', 'contact_email' => 'email']);
});
