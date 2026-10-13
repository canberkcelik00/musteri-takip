<?php

namespace App\Enums;

enum SettingKey: string
{
    case RenewalLeadDays = 'renewal_lead_days';
    case DigestTime = 'digest_time';
    case NotificationEmail = 'notification_email';
    case DigestFrequency = 'digest_frequency';
    case DigestDayOfMonth = 'digest_day_of_month';

    /**
     * Internal state, not shown on the settings page: when the digest was last handled.
     */
    case DigestLastRunAt = 'digest_last_run_at';

    /**
     * The value used while no setting is stored.
     */
    public function default(): ?string
    {
        return match ($this) {
            self::RenewalLeadDays => '30',
            self::DigestTime => '09:00',
            self::NotificationEmail => null,
            self::DigestFrequency => 'monthly',
            self::DigestDayOfMonth => '15',
            self::DigestLastRunAt => null,
        };
    }
}
