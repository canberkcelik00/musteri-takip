<?php

namespace App\Actions\Payments;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Customer;
use App\Models\PaymentMethod;

class SuggestPaymentDefaults
{
    /**
     * Defaults of the payment form for a customer, without saving anything.
     *
     * The currency is the one of the customer's oldest open charge (due date, then id), TL when
     * there is none. The method is the one of the customer's latest payment; when the customer
     * has no payment yet or that method is archived, the first method in the list order.
     *
     * @return array{currency: Currency, payment_method_id: ?int}
     */
    public function handle(?Customer $customer): array
    {
        return [
            'currency' => $this->currency($customer),
            'payment_method_id' => $this->paymentMethodId($customer),
        ];
    }

    private function currency(?Customer $customer): Currency
    {
        if ($customer === null) {
            return Currency::Try;
        }

        $charges = $customer->charges()
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($charges as $charge) {
            if ($charge->remainingAmount() > 0) {
                return $charge->currency;
            }
        }

        return Currency::Try;
    }

    private function paymentMethodId(?Customer $customer): ?int
    {
        $latestMethodId = $customer?->payments()
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->value('payment_method_id');

        if ($latestMethodId !== null && PaymentMethod::query()->notArchived()->whereKey($latestMethodId)->exists()) {
            return (int) $latestMethodId;
        }

        $first = PaymentMethod::query()->notArchived()->orderBy('sort_order')->orderBy('id')->value('id');

        return $first === null ? null : (int) $first;
    }
}
