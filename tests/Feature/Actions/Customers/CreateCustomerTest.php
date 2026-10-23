<?php

use App\Actions\Customers\CreateCustomer;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\QueryException;

it('creates a customer with its first contact as the primary contact', function () {
    $customer = app(CreateCustomer::class)->handle(
        ['short_name' => 'Logi', 'legal_title' => 'Logitech Ltd. Şti.', 'notes' => 'Not'],
        ['name' => 'Murat Kaya', 'title' => 'Bilgi İşlem', 'phone' => '0111 000 00 00', 'email' => 'murat@example.com'],
    );

    expect($customer->short_name)->toBe('Logi')
        ->and($customer->legal_title)->toBe('Logitech Ltd. Şti.')
        ->and($customer->contacts)->toHaveCount(1)
        ->and($customer->primaryContact->name)->toBe('Murat Kaya')
        ->and($customer->primaryContact->email)->toBe('murat@example.com');
});

it('does not create the customer when its first contact cannot be created', function () {
    expect(fn () => app(CreateCustomer::class)->handle(['short_name' => 'Logi'], ['title' => 'Adsız']))
        ->toThrow(QueryException::class);

    expect(Customer::count())->toBe(0)
        ->and(Contact::count())->toBe(0);
});
