<?php

namespace App\Actions\Payments;

use App\Exceptions\PaymentCannotBeUndone;
use App\Models\Payment;
use App\Models\User;

class UndoPayment
{
    /** Minutes after recording during which the user who recorded a payment can take it back. */
    public const int WINDOW_MINUTES = 2;

    public function __construct(private DeletePayment $deletePayment) {}

    /**
     * Take back a payment the actor has just recorded: only the user who recorded it, and only within a couple of
     * minutes. Later mistakes are fixed from the payments list (DeletePayment).
     *
     * Returns how many paid charges are open again afterwards, for the notification.
     *
     * @throws PaymentCannotBeUndone
     */
    public function handle(User $actor, Payment $payment): int
    {
        if ($payment->recorded_by !== $actor->id || $payment->created_at->lt(now()->subMinutes(self::WINDOW_MINUTES))) {
            throw PaymentCannotBeUndone::notAvailable();
        }

        return $this->deletePayment->handle($payment);
    }
}
