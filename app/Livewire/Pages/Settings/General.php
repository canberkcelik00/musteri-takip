<?php

namespace App\Livewire\Pages\Settings;

use App\Actions\Settings\SendTestEmail;
use App\Actions\Settings\UpdateSettings;
use App\Enums\DigestFrequency;
use App\Enums\RefreshIntervalUnit;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * The general settings form (docs/UI.md §5 Ayarlar). The values are validated and stored by UpdateSettings, so the
 * property names are the keys it validates and a failure shows under the right field. The logo is uploaded to the
 * `local` disk under the company directory; UpdateSettings deletes the file it replaces. It only calls UpdateSettings
 * and SendTestEmail.
 */
#[Layout('layouts.app')]
class General extends Component
{
    use WithFileUploads;

    public string $renewal_lead_days = '';

    public string $digest_frequency = '';

    public string $digest_day_of_month = '';

    public string $digest_time = '';

    public string $notification_email = '';

    public string $usage_warning_percent = '';

    public string $usage_refresh_value = '';

    public string $usage_refresh_unit = '';

    public bool $customer_reminders_enabled = false;

    public string $quote_validity_days = '';

    public string $quote_bcc_email = '';

    public string $company_name = '';

    public string $company_address = '';

    public string $company_phone = '';

    public string $company_email = '';

    public string $company_tax_office = '';

    public string $company_tax_number = '';

    public string $company_iban = '';

    /** The logo path stored now; only changed by a save. */
    #[Locked]
    public ?string $company_logo = null;

    /** @var mixed A freshly uploaded logo, stored when the form is saved. */
    public $logo = null;

    public bool $remove_logo = false;

    public function mount(Settings $settings): void
    {
        [$refreshValue, $refreshUnit] = RefreshIntervalUnit::split($settings->usageRefreshMinutes());
        $company = $settings->companyDetails();

        $this->renewal_lead_days = (string) $settings->renewalLeadDays();
        $this->digest_frequency = $settings->digestFrequency()->value;
        $this->digest_day_of_month = (string) $settings->digestDayOfMonth();
        $this->digest_time = $settings->digestTime();
        $this->notification_email = (string) $settings->notificationEmail();
        $this->usage_warning_percent = (string) $settings->usageWarningPercent();
        $this->usage_refresh_value = (string) $refreshValue;
        $this->usage_refresh_unit = $refreshUnit->value;
        $this->customer_reminders_enabled = $settings->customerRemindersEnabled();
        $this->quote_validity_days = (string) $settings->quoteValidityDays();
        $this->quote_bcc_email = (string) $settings->quoteBccEmail();
        $this->company_name = (string) $company['name'];
        $this->company_address = (string) $company['address'];
        $this->company_phone = (string) $company['phone'];
        $this->company_email = (string) $company['email'];
        $this->company_tax_office = (string) $company['tax_office'];
        $this->company_tax_number = (string) $company['tax_number'];
        $this->company_iban = (string) $company['iban'];
        $this->company_logo = $company['logo_path'];
    }

    public function save(UpdateSettings $updateSettings): void
    {
        $this->validate(['logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,gif', 'max:1024']], attributes: ['logo' => __('settings.company_logo')]);

        $logo = $this->company_logo;

        if ($this->logo !== null) {
            $logo = $this->logo->store(UpdateSettings::LOGO_DIRECTORY, 'local');
        } elseif ($this->remove_logo) {
            $logo = null;
        }

        $updateSettings->handle([
            'renewal_lead_days' => $this->renewal_lead_days,
            'digest_frequency' => $this->digest_frequency,
            'digest_day_of_month' => $this->digest_day_of_month,
            'digest_time' => $this->digest_time,
            'notification_email' => $this->notification_email,
            'usage_warning_percent' => $this->usage_warning_percent,
            'usage_refresh_value' => $this->usage_refresh_value,
            'usage_refresh_unit' => $this->usage_refresh_unit,
            'customer_reminders_enabled' => $this->customer_reminders_enabled,
            'quote_validity_days' => $this->quote_validity_days,
            'quote_bcc_email' => $this->quote_bcc_email,
            'company_name' => $this->company_name,
            'company_address' => $this->company_address,
            'company_phone' => $this->company_phone,
            'company_email' => $this->company_email,
            'company_tax_office' => $this->company_tax_office,
            'company_tax_number' => $this->company_tax_number,
            'company_iban' => $this->company_iban,
            'company_logo' => $logo,
        ]);

        $this->company_logo = $logo;
        $this->logo = null;
        $this->remove_logo = false;
        $this->company_iban = strtoupper((string) preg_replace('/\s+/', '', $this->company_iban));

        $this->dispatch('notify', message: __('settings.saved'));
    }

    public function sendTestEmail(SendTestEmail $sendTestEmail): void
    {
        try {
            $recipient = $sendTestEmail->handle();
        } catch (Throwable $exception) {
            report($exception);

            $this->dispatch('notify', message: __('settings.test_email_failed', ['message' => $sendTestEmail->describeFailure($exception)]), tone: 'danger');

            return;
        }

        if ($recipient === null) {
            $this->dispatch('notify', message: __('settings.test_email_no_address'), tone: 'danger');

            return;
        }

        $message = __('settings.test_email_sent', ['email' => $recipient]);

        if (trim($this->notification_email) !== $recipient) {
            $message .= ' '.__('settings.test_email_unsaved');
        }

        $this->dispatch('notify', message: $message);
    }

    public function render(): View
    {
        return view('livewire.pages.settings.general', [
            'frequencies' => collect(DigestFrequency::cases())->mapWithKeys(fn (DigestFrequency $case): array => [$case->value => $case->getLabel()])->all(),
            'timeOptions' => UpdateSettings::digestTimeOptions(),
            'units' => collect(RefreshIntervalUnit::cases())->mapWithKeys(fn (RefreshIntervalUnit $case): array => [$case->value => $case->getLabel()])->all(),
        ]);
    }
}
