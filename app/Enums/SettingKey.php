<?php

namespace App\Enums;

enum SettingKey: string
{
    case RenewalLeadDays = 'renewal_lead_days';
    case DigestTime = 'digest_time';
    case NotificationEmail = 'notification_email';

    /**
     * The value used while no setting is stored.
     */
    public function default(): ?string
    {
        return match ($this) {
            self::RenewalLeadDays => '30',
            self::DigestTime => '09:00',
            self::NotificationEmail => null,
        };
    }
}
