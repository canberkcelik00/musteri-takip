<?php

use App\Enums\AllocationSource;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use Filament\Support\Contracts\HasLabel;

it('has the values and Turkish labels in the data model', function (string $enum, array $labels) {
    /** @var class-string<BackedEnum&HasLabel> $enum */
    $actual = [];

    foreach ($enum::cases() as $case) {
        $actual[$case->value] = $case->getLabel();
    }

    expect($actual)->toBe($labels);
})->with([
    'currency' => [Currency::class, ['TRY' => 'TL', 'USD' => 'USD', 'EUR' => 'EUR']],
    'customer service status' => [CustomerServiceStatus::class, ['active' => 'Aktif', 'cancelled' => 'İptal edildi']],
    'charge status' => [ChargeStatus::class, ['open' => 'Açık', 'paid' => 'Ödendi', 'cancelled' => 'İptal edildi']],
    'allocation source' => [AllocationSource::class, ['payment' => 'Ödeme', 'credit_balance' => 'Alacak bakiyesi']],
]);
