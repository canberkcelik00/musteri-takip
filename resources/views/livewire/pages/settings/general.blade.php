<div>
    <x-ui.page-header :title="__('settings.title')" />

    <form wire:submit="save" class="ui-card ui-pane mt-5">
        <div class="ui-form">
            <x-ui.input type="number" :label="__('settings.renewal_lead_days')" :hint="__('settings.renewal_lead_days_help')" wire:model="renewal_lead_days" min="1" max="365" required />
            <x-ui.input type="number" :label="__('settings.usage_warning_percent')" :hint="__('settings.usage_warning_percent_help')" suffix="%" wire:model="usage_warning_percent" min="1" max="100" required />

            <x-ui.select :label="__('settings.digest_frequency')" wire:model.live="digest_frequency" :options="$frequencies" />
            <x-ui.select :label="__('settings.digest_time')" wire:model="digest_time" :options="$timeOptions" :searchable="true" />

            @if ($digest_frequency === \App\Enums\DigestFrequency::Monthly->value)
                <div class="is-full">
                    <x-ui.input type="number" :label="__('settings.digest_day_of_month')" :hint="__('settings.digest_day_of_month_help')" wire:model="digest_day_of_month" min="1" max="28" required />
                </div>
            @endif
            <span class="ui-hint is-full">{{ __('settings.digest_time_help') }}</span>

            <div class="is-full">
                <x-ui.input type="email" :label="__('settings.notification_email')" :hint="__('settings.notification_email_help')" wire:model="notification_email" maxlength="255" />
            </div>

            <x-ui.input type="number" :label="__('settings.usage_refresh_interval')" wire:model="usage_refresh_value" min="1" max="43200" required />
            <x-ui.select :label="__('settings.usage_refresh_unit')" wire:model="usage_refresh_unit" :options="$units" />
            <span class="ui-hint is-full">{{ __('settings.usage_refresh_interval_help') }}</span>
            @error('usage_refresh_value')
                <span class="ui-error is-full">{{ $message }}</span>
            @enderror

            <div class="is-full">
                <x-ui.toggle wire:model="customer_reminders_enabled" :label="__('settings.customer_reminders_enabled')" />
                <span class="ui-hint">{{ __('settings.customer_reminders_enabled_help') }}</span>
            </div>

            <h3 class="ui-form-section is-full">{{ __('settings.quote_section') }}</h3>
            <x-ui.input type="number" :label="__('settings.quote_validity_days')" :hint="__('settings.quote_validity_days_help')" wire:model="quote_validity_days" min="1" max="365" required />
            <x-ui.input type="email" :label="__('settings.quote_bcc_email')" :hint="__('settings.quote_bcc_email_help')" wire:model="quote_bcc_email" maxlength="255" />

            <div class="is-full">
                <h3 class="ui-form-section">{{ __('settings.company_section') }}</h3>
                <p class="ui-hint">{{ __('settings.company_section_help') }}</p>
            </div>
            <div class="is-full"><x-ui.input :label="__('settings.company_name')" wire:model="company_name" maxlength="255" /></div>
            <div class="is-full"><x-ui.input type="textarea" :label="__('settings.company_address')" wire:model="company_address" maxlength="1000" /></div>
            <x-ui.input type="tel" :label="__('settings.company_phone')" wire:model="company_phone" maxlength="255" />
            <x-ui.input type="email" :label="__('settings.company_email')" wire:model="company_email" maxlength="255" />
            <x-ui.input :label="__('settings.company_tax_office')" wire:model="company_tax_office" maxlength="255" />
            <x-ui.input :label="__('settings.company_tax_number')" wire:model="company_tax_number" maxlength="255" />
            <div class="is-full"><x-ui.input :label="__('settings.company_iban')" :hint="__('settings.company_iban_help')" wire:model="company_iban" maxlength="40" /></div>

            <div class="ui-field is-full">
                <span class="ui-label">{{ __('settings.company_logo') }}</span>
                @if ($company_logo !== null && ! $remove_logo)
                    <span class="ui-sub-line">{{ basename($company_logo) }}</span>
                    <div><x-ui.button size="sm" wire:click="$set('remove_logo', true)">{{ __('actions.remove') }}</x-ui.button></div>
                @endif
                <x-ui.file-input wire:model="logo" accept="image/png,image/jpeg,image/gif" :hint="__('settings.company_logo_help')" />
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <x-ui.button wire:click="sendTestEmail" type="button">{{ __('settings.send_test_email') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary">{{ __('settings.save') }}</x-ui.button>
        </div>
    </form>
</div>
