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
     * When the digest was last handled (sent, found empty, or first started), in the
     * application time zone; null before the first run.
     */
    public function digestLastRunAt(): ?Carbon
    {
        $key = SettingKey::DigestLastRunAt;
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

    /**
     * Record the moment the digest was handled.
     */
    public function markDigestRun(CarbonInterface $moment): void
    {
        $this->set(SettingKey::DigestLastRunAt, Carbon::instance($moment)->setTimezone(config('app.timezone'))->toIso8601String());
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
