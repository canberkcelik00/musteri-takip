<?php

namespace App\Actions\Settings;

use App\Enums\DigestFrequency;
use App\Enums\RefreshIntervalUnit;
use App\Enums\SettingKey;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateSettings
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * The digest is sent on the first scheduler run after this time and the cron runs
     * every 15 minutes, so the times are offered in 15 minute steps.
     *
     * @return array<string, string>
     */
    public static function digestTimeOptions(): array
    {
        $options = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += 15) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $options[$time] = $time;
        }

        return $options;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'renewal_lead_days' => ['required', 'integer', 'min:1', 'max:365'],
            'digest_frequency' => ['required', Rule::enum(DigestFrequency::class)],
            'digest_day_of_month' => ['nullable', 'required_if:digest_frequency,'.DigestFrequency::Monthly->value, 'integer', 'min:1', 'max:28'],
            'digest_time' => ['required', Rule::in(array_keys(self::digestTimeOptions()))],
            'notification_email' => ['nullable', 'string', 'email', 'max:255'],
            'customer_reminders_enabled' => ['sometimes', 'boolean'],
            'usage_warning_percent' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'usage_refresh_value' => ['sometimes', 'required', 'integer', 'min:1', 'max:43200'],
            'usage_refresh_unit' => ['required_with:usage_refresh_value', Rule::enum(RefreshIntervalUnit::class)],
        ];
    }

    /**
     * Validate and store the settings; nothing is written when a value is invalid. A blank
     * notification address removes the setting, so no digest recipient is set. A missing
     * reminder switch or usage warning percent keeps the stored one. The day of the
     * month is stored only for the monthly digest; with the daily one the stored day is kept,
     * so it comes back when monthly is chosen again.
     *
     * The usage refresh interval comes as a number and a unit and is stored as minutes, 15
     * minutes to 30 days; a missing one keeps the stored interval.
     *
     * @param  array{renewal_lead_days: int|string|null, digest_frequency: DigestFrequency|string|null, digest_day_of_month?: int|string|null, digest_time: string|null, notification_email?: string|null, customer_reminders_enabled?: bool|int|string|null, usage_warning_percent?: int|string|null, usage_refresh_value?: int|string|null, usage_refresh_unit?: RefreshIntervalUnit|string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): void
    {
        $email = trim((string) ($data['notification_email'] ?? ''));
        $data['notification_email'] = $email === '' ? null : $email;

        if (($data['digest_frequency'] ?? null) instanceof DigestFrequency) {
            $data['digest_frequency'] = $data['digest_frequency']->value;
        }

        if (($data['usage_refresh_unit'] ?? null) instanceof RefreshIntervalUnit) {
            $data['usage_refresh_unit'] = $data['usage_refresh_unit']->value;
        }

        $validated = Validator::make($data, self::rules())->validate();
        $frequency = DigestFrequency::from($validated['digest_frequency']);
        $refreshMinutes = isset($validated['usage_refresh_value'])
            ? RefreshIntervalUnit::from($validated['usage_refresh_unit'])->minutes((int) $validated['usage_refresh_value'])
            : null;

        if ($refreshMinutes !== null && ($refreshMinutes < 15 || $refreshMinutes > 43200)) {
            throw ValidationException::withMessages(['usage_refresh_value' => __('settings.usage_refresh_interval_range')]);
        }

        DB::transaction(function () use ($validated, $frequency, $refreshMinutes): void {
            $this->settings->set(SettingKey::RenewalLeadDays, (string) $validated['renewal_lead_days']);
            $this->settings->set(SettingKey::DigestFrequency, $frequency->value);

            if ($frequency === DigestFrequency::Monthly) {
                $this->settings->set(SettingKey::DigestDayOfMonth, (string) $validated['digest_day_of_month']);
            }

            $this->settings->set(SettingKey::DigestTime, $validated['digest_time']);
            $this->settings->set(SettingKey::NotificationEmail, $validated['notification_email']);

            if (array_key_exists('customer_reminders_enabled', $validated)) {
                $this->settings->set(SettingKey::CustomerRemindersEnabled, $validated['customer_reminders_enabled'] ? '1' : '0');
            }

            if (array_key_exists('usage_warning_percent', $validated)) {
                $this->settings->set(SettingKey::UsageWarningPercent, (string) $validated['usage_warning_percent']);
            }

            if ($refreshMinutes !== null) {
                $this->settings->set(SettingKey::UsageRefreshMinutes, (string) $refreshMinutes);
            }
        });
    }
}
