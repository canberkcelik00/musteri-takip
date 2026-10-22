<?php

namespace App\Support;

use App\Enums\DigestFrequency;
use App\Enums\SettingKey;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Key–value application settings with defaults.
 *
 * Values are read from the database on every call (no cache), so a changed
 * setting takes effect immediately. Typed readers fall back to the default
 * and log a warning when the stored value is invalid.
 */
class Settings
{
    /**
     * The keys of the company details JSON.
     */
    public const COMPANY_DETAIL_KEYS = ['name', 'address', 'phone', 'email', 'tax_office', 'tax_number', 'logo_path'];

    /**
     * Get the stored value, or the default when nothing is stored.
     */
    public function get(SettingKey $key): ?string
    {
        $value = Setting::query()->where('key', $key->value)->value('value');

        return $value ?? $key->default();
    }

    /**
     * Store a value; null removes it so the default applies again.
     */
    public function set(SettingKey $key, ?string $value): void
    {
        if ($value === null) {
            Setting::query()->where('key', $key->value)->delete();

            return;
        }

        Setting::query()->updateOrCreate(['key' => $key->value], ['value' => $value]);
    }

    /**
     * Days before the end date when the renewal charge is created (1–365).
     */
    public function renewalLeadDays(): int
    {
        $key = SettingKey::RenewalLeadDays;
        $days = filter_var($this->get($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 365],
        ]);

        if ($days === false) {
            return (int) $this->fallback($key);
        }

        return $days;
    }

    /**
     * Time of the daily digest e-mail in HH:MM format.
     */
    public function digestTime(): string
    {
        $key = SettingKey::DigestTime;
        $time = (string) $this->get($key);

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return (string) $this->fallback($key);
        }

        return $time;
    }

    /**
     * Address the daily digest is sent to, or null when not set.
     */
    public function notificationEmail(): ?string
    {
        $key = SettingKey::NotificationEmail;
        $email = $this->get($key);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fallback($key);
        }

        return $email;
    }

    /**
     * How often the digest e-mail is sent.
     */
    public function digestFrequency(): DigestFrequency
    {
        $key = SettingKey::DigestFrequency;
        $frequency = DigestFrequency::tryFrom((string) $this->get($key));

        return $frequency ?? DigestFrequency::from((string) $this->fallback($key));
    }

    /**
     * Day of the month (1–28) the monthly digest is sent on; 29–31 do not exist in every month.
     */
    public function digestDayOfMonth(): int
    {
        $key = SettingKey::DigestDayOfMonth;
        $day = filter_var($this->get($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 28],
        ]);

        if ($day === false) {
            return (int) $this->fallback($key);
        }

        return $day;
    }

    /**
     * Percent of a limit (1–100) from which a hosting account's disk, traffic, counter or mailbox
     * counts as nearly full (Faz 9); the one threshold behind every usage warning.
     */
    public function usageWarningPercent(): int
    {
        $key = SettingKey::UsageWarningPercent;
        $percent = filter_var($this->get($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);

        if ($percent === false) {
            return (int) $this->fallback($key);
        }

        return $percent;
    }

    /**
     * How often, in minutes, the usage of a linked hosting account is read from its server
     * (15 minutes to 30 days, Faz 9); the scheduler runs every 15 minutes at the shortest.
     */
    public function usageRefreshMinutes(): int
    {
        $key = SettingKey::UsageRefreshMinutes;
        $minutes = filter_var($this->get($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 15, 'max_range' => 43200],
        ]);

        if ($minutes === false) {
            return (int) $this->fallback($key);
        }

        return $minutes;
    }

    /**
     * Days a quote stays valid from the day it is created or re-sent (1–365, Faz 6).
     */
    public function quoteValidityDays(): int
    {
        $key = SettingKey::QuoteValidityDays;
        $days = filter_var($this->get($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 365],
        ]);

        if ($days === false) {
            return (int) $this->fallback($key);
        }

        return $days;
    }

    /**
     * Address that gets a copy of every sent quote, or null when not set (Faz 6).
     */
    public function quoteBccEmail(): ?string
    {
        $key = SettingKey::QuoteBccEmail;
        $email = $this->get($key);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fallback($key);
        }

        return $email;
    }

    /**
     * Company details printed on the quote PDF, always with every key of
     * {@see self::COMPANY_DETAIL_KEYS}; a missing or unreadable value gives all-null.
     * `logo_path` is a path under `storage/app`.
     *
     * @return array{name: ?string, address: ?string, phone: ?string, email: ?string, tax_office: ?string, tax_number: ?string, logo_path: ?string}
     */
    public function companyDetails(): array
    {
        $key = SettingKey::CompanyDetails;
        $stored = $this->get($key);
        $decoded = $stored === null ? [] : json_decode($stored, true);

        if (! is_array($decoded)) {
            $this->fallback($key);
            $decoded = [];
        }

        $text = fn (string $detailKey): ?string => is_string($decoded[$detailKey] ?? null) && $decoded[$detailKey] !== ''
            ? $decoded[$detailKey]
            : null;

        return [
            'name' => $text('name'),
            'address' => $text('address'),
            'phone' => $text('phone'),
            'email' => $text('email'),
            'tax_office' => $text('tax_office'),
            'tax_number' => $text('tax_number'),
            'logo_path' => $text('logo_path'),
        ];
    }

    /**
     * Whether renewal reminders are sent to the customers' primary contacts (G-5B.1); off until
     * the owner turns it on.
     */
    public function customerRemindersEnabled(): bool
    {
        $key = SettingKey::CustomerRemindersEnabled;
        $value = $this->get($key);

        if ($value === '1' || $value === '0') {
            return $value === '1';
        }

        $this->fallback($key);

        return false;
    }

    /**
     * When the digest was last handled (sent, found empty, or first started), in the
     * application time zone; null before the first run.
     */
    public function digestLastRunAt(): ?Carbon
    {
        return $this->moment(SettingKey::DigestLastRunAt);
    }

    /**
     * Record the moment the digest was handled.
     */
    public function markDigestRun(CarbonInterface $moment): void
    {
        $this->setMoment(SettingKey::DigestLastRunAt, $moment);
    }

    /**
     * When app:process-customer-services last finished, in the application time zone; null
     * before the first run.
     */
    public function schedulerLastRunAt(): ?Carbon
    {
        return $this->moment(SettingKey::SchedulerLastRunAt);
    }

    /**
     * The ids of the customer services that failed in the last scheduler run.
     *
     * @return list<int>
     */
    public function schedulerFailedServiceIds(): array
    {
        $key = SettingKey::SchedulerFailedServiceIds;
        $value = $this->get($key);

        if ($value === null) {
            return [];
        }

        $ids = json_decode($value, true);

        if (! is_array($ids) || ! array_is_list($ids) || array_filter($ids, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            $this->fallback($key);

            return [];
        }

        /** @var list<int> $ids */
        return $ids;
    }

    /**
     * Record the moment the scheduler run finished and the customer services that failed in it;
     * a run without failures removes the previous run's list.
     *
     * @param  list<int>  $failedServiceIds
     */
    public function markSchedulerRun(CarbonInterface $moment, array $failedServiceIds): void
    {
        $this->setMoment(SettingKey::SchedulerLastRunAt, $moment);
        $this->set(SettingKey::SchedulerFailedServiceIds, $failedServiceIds === [] ? null : (string) json_encode($failedServiceIds));
    }

    /**
     * Read a stored ISO date-time in the application time zone; an unreadable value is logged
     * and read as none.
     */
    private function moment(SettingKey $key): ?Carbon
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (InvalidFormatException) {
            $this->fallback($key);

            return null;
        }
    }

    private function setMoment(SettingKey $key, CarbonInterface $moment): void
    {
        $this->set($key, Carbon::instance($moment)->setTimezone(config('app.timezone'))->toIso8601String());
    }

    /**
     * Log the invalid stored value and return the default.
     */
    private function fallback(SettingKey $key): ?string
    {
        Log::warning('Invalid setting value, using the default.', [
            'key' => $key->value,
            'value' => $this->get($key),
        ]);

        return $key->default();
    }
}
