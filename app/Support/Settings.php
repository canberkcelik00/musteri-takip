<?php

namespace App\Support;

use App\Enums\SettingKey;
use App\Models\Setting;
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
