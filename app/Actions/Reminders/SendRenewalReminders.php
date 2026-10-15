<?php

namespace App\Actions\Reminders;

use App\Models\Charge;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Send the renewal reminder of every renewal charge that awaits one ("henüz yapılmadıysa yap",
 * G-5B.1). Called on every scheduler run: a charge is reminded once, on the first run that finds
 * it, however many runs were missed. Does nothing while the setting is off.
 *
 * Each charge is handled on its own. A failed send is logged and records nothing, so the next run
 * tries again; the other charges are still sent. A charge whose customer's primary contact has no
 * usable e-mail address is skipped without a record (the digest warns about it) and is sent on the
 * run after the address is fixed.
 */
class SendRenewalReminders
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SendRenewalReminder $sendRenewalReminder,
    ) {}

    /**
     * The address a charge's reminder goes to: the customer's primary contact's e-mail when it is
     * a valid address, otherwise null. Shared with the digest warning so that both agree.
     */
    public static function recipientFor(Charge $charge): ?string
    {
        $email = trim((string) $charge->customer->primaryContact?->email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function handle(): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->settings->customerRemindersEnabled()) {
            return $result;
        }

        Charge::query()
            ->awaitingReminder()
            ->with(['customer.primaryContact', 'customerService'])
            ->withSum('allocations', 'amount')
            ->chunkById(100, function (Collection $charges) use (&$result): void {
                foreach ($charges as $charge) {
                    if (self::recipientFor($charge) === null) {
                        $result['skipped']++;

                        continue;
                    }

                    try {
                        $this->sendRenewalReminder->handle($charge);
                    } catch (Throwable $exception) {
                        Log::error('Renewal reminder could not be sent.', [
                            'charge_id' => $charge->id,
                            'customer_id' => $charge->customer_id,
                            'exception' => $exception,
                        ]);

                        $result['failed']++;

                        continue;
                    }

                    $result['sent']++;
                }
            });

        return $result;
    }
}
