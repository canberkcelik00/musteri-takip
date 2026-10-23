<?php

namespace App\Actions\Settings;

use App\Enums\DigestFrequency;
use App\Enums\RefreshIntervalUnit;
use App\Enums\SettingKey;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateSettings
{
    /**
     * The company logo is stored on the `local` disk in this directory under a generated name; no
     * other path is accepted, so a tampered form value can neither point at nor delete another file.
     */
    public const string LOGO_DIRECTORY = 'company';

    private const string LOGO_PATH_PATTERN = '/^company\/[A-Za-z0-9._-]+$/';

    private const string IBAN_PATTERN = '/^TR\d{24}$/';

    private const array COMPANY_FIELDS = [
        'company_name' => 'name',
        'company_address' => 'address',
        'company_phone' => 'phone',
        'company_email' => 'email',
        'company_tax_office' => 'tax_office',
        'company_tax_number' => 'tax_number',
        'company_iban' => 'iban',
        'company_logo' => 'logo_path',
    ];

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
            'quote_validity_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'quote_bcc_email' => ['nullable', 'string', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'string', 'email', 'max:255'],
            'company_tax_office' => ['nullable', 'string', 'max:255'],
            'company_tax_number' => ['nullable', 'string', 'max:255'],
            'company_iban' => ['nullable', 'string', 'regex:'.self::IBAN_PATTERN],
            'company_logo' => ['nullable', 'string', 'regex:'.self::LOGO_PATH_PATTERN],
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
     * The quote validity days, quote BCC address and company details (G-6.7) are each stored only
     * when given; a given blank value removes it. The company details are one JSON setting that
     * keeps the fields not given.
     *
     * @param  array{renewal_lead_days: int|string|null, digest_frequency: DigestFrequency|string|null, digest_day_of_month?: int|string|null, digest_time: string|null, notification_email?: string|null, customer_reminders_enabled?: bool|int|string|null, usage_warning_percent?: int|string|null, usage_refresh_value?: int|string|null, usage_refresh_unit?: RefreshIntervalUnit|string|null, quote_validity_days?: int|string|null, quote_bcc_email?: ?string, company_name?: ?string, company_address?: ?string, company_phone?: ?string, company_email?: ?string, company_tax_office?: ?string, company_tax_number?: ?string, company_iban?: ?string, company_logo?: string|array<int|string, string>|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): void
    {
        $email = trim((string) ($data['notification_email'] ?? ''));
        $data['notification_email'] = $email === '' ? null : $email;
        $data = $this->normalizeQuoteFields($data);

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

            if (array_key_exists('quote_validity_days', $validated)) {
                $this->settings->set(SettingKey::QuoteValidityDays, (string) $validated['quote_validity_days']);
            }

            if (array_key_exists('quote_bcc_email', $validated)) {
                $this->settings->set(SettingKey::QuoteBccEmail, $validated['quote_bcc_email']);
            }
        });

        $this->storeCompanyDetails($validated);
    }

    /**
     * Blank quote and company values count as none (so a cleared field removes the value); the
     * logo may arrive as a one-item list from the upload field. The IBAN is stored without spaces
     * and in capitals, so it validates and prints the same however it was typed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeQuoteFields(array $data): array
    {
        foreach (['quote_bcc_email', ...array_keys(self::COMPANY_FIELDS)] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = is_array($data[$field]) ? (array_values($data[$field])[0] ?? null) : $data[$field];
            $value = trim((string) $value);
            $data[$field] = $value === '' ? null : $value;
        }

        if (is_string($data['company_iban'] ?? null)) {
            $data['company_iban'] = strtoupper((string) preg_replace('/\s+/', '', $data['company_iban']));
        }

        return $data;
    }

    /**
     * Merge the given company fields into the stored company details (a field that is not given
     * keeps its value) and store them as JSON, or remove the setting when nothing is left. A logo
     * that is replaced or removed has its file deleted from the `local` disk.
     *
     * @param  array<string, mixed>  $validated
     */
    private function storeCompanyDetails(array $validated): void
    {
        $given = array_intersect_key(self::COMPANY_FIELDS, $validated);

        if ($given === []) {
            return;
        }

        $details = $this->settings->companyDetails();
        $oldLogo = $details['logo_path'];

        foreach ($given as $field => $key) {
            $details[$key] = $validated[$field];
        }

        $details = array_filter($details, fn (?string $value): bool => $value !== null);

        $this->settings->set(SettingKey::CompanyDetails, $details === [] ? null : (string) json_encode($details, JSON_UNESCAPED_UNICODE));

        if ($oldLogo !== null && $oldLogo !== ($details['logo_path'] ?? null) && preg_match(self::LOGO_PATH_PATTERN, $oldLogo) === 1) {
            Storage::disk('local')->delete($oldLogo);
        }
    }
}
