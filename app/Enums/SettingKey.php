<?php

namespace App\Enums;

enum SettingKey: string
{
    case RenewalLeadDays = 'renewal_lead_days';
    case DigestTime = 'digest_time';
    case NotificationEmail = 'notification_email';
    case DigestFrequency = 'digest_frequency';
    case DigestDayOfMonth = 'digest_day_of_month';
    case CustomerRemindersEnabled = 'customer_reminders_enabled';
    case UsageWarningPercent = 'usage_warning_percent';
    case UsageRefreshMinutes = 'usage_refresh_minutes';

    /**
     * Internal state, not shown on the settings page: when the digest was last handled.
     */
    case DigestLastRunAt = 'digest_last_run_at';

    /**
     * Internal state, not shown on the settings page: when app:process-customer-services last finished.
     */
    case SchedulerLastRunAt = 'scheduler_last_run_at';

    /**
     * Internal state, not shown on the settings page: the customer services that failed in
     * that run, as a JSON list of ids; removed when the run had no failures.
     */
    case SchedulerFailedServiceIds = 'scheduler_failed_service_ids';

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
            self::CustomerRemindersEnabled => '0',
            self::UsageWarningPercent => '90',
            self::UsageRefreshMinutes => '60',
            self::DigestLastRunAt => null,
            self::SchedulerLastRunAt => null,
            self::SchedulerFailedServiceIds => null,
        };
    }
}
