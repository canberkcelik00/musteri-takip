<?php

namespace App\Filament\Pages;

use App\Actions\Settings\SendTestEmail;
use App\Actions\Settings\UpdateSettings;
use App\Enums\DigestFrequency;
use App\Enums\RefreshIntervalUnit;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * The general settings form; the values are written by UpdateSettings and read by the
 * rules through App\Support\Settings.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.general');
    }

    public function getTitle(): string|Htmlable
    {
        return __('settings.title');
    }

    public function mount(Settings $settings): void
    {
        [$refreshValue, $refreshUnit] = RefreshIntervalUnit::split($settings->usageRefreshMinutes());
        $company = $settings->companyDetails();

        $this->form->fill([
            'quote_validity_days' => $settings->quoteValidityDays(),
            'quote_bcc_email' => $settings->quoteBccEmail(),
            'company_name' => $company['name'],
            'company_address' => $company['address'],
            'company_phone' => $company['phone'],
            'company_email' => $company['email'],
            'company_tax_office' => $company['tax_office'],
            'company_tax_number' => $company['tax_number'],
            'company_iban' => $company['iban'],
            'company_logo' => $company['logo_path'],
            'usage_refresh_value' => $refreshValue,
            'usage_refresh_unit' => $refreshUnit,
            'renewal_lead_days' => $settings->renewalLeadDays(),
            'digest_frequency' => $settings->digestFrequency(),
            'digest_day_of_month' => $settings->digestDayOfMonth(),
            'digest_time' => $settings->digestTime(),
            'notification_email' => $settings->notificationEmail(),
            'customer_reminders_enabled' => $settings->customerRemindersEnabled(),
            'usage_warning_percent' => $settings->usageWarningPercent(),
        ]);
    }

    /**
     * The form state holds the enum, or its value while the select is being changed.
     */
    private static function isMonthly(mixed $frequency): bool
    {
        return $frequency === DigestFrequency::Monthly || $frequency === DigestFrequency::Monthly->value;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('renewal_lead_days')
                    ->label(__('settings.renewal_lead_days'))
                    ->helperText(__('settings.renewal_lead_days_help'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->required(),
                Select::make('digest_frequency')
                    ->label(__('settings.digest_frequency'))
                    ->options(DigestFrequency::class)
                    ->native(false)
                    ->live()
                    ->required(),
                TextInput::make('digest_day_of_month')
                    ->label(__('settings.digest_day_of_month'))
                    ->helperText(__('settings.digest_day_of_month_help'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(28)
                    ->visible(fn (Get $get): bool => self::isMonthly($get('digest_frequency')))
                    ->required(fn (Get $get): bool => self::isMonthly($get('digest_frequency'))),
                Select::make('digest_time')
                    ->label(__('settings.digest_time'))
                    ->helperText(__('settings.digest_time_help'))
                    ->options(UpdateSettings::digestTimeOptions())
                    ->native(false)
                    ->required(),
                TextInput::make('notification_email')
                    ->label(__('settings.notification_email'))
                    ->helperText(__('settings.notification_email_help'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('usage_warning_percent')
                    ->label(__('settings.usage_warning_percent'))
                    ->helperText(__('settings.usage_warning_percent_help'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(),
                Grid::make(2)->schema([
                    TextInput::make('usage_refresh_value')
                        ->label(__('settings.usage_refresh_interval'))
                        ->helperText(__('settings.usage_refresh_interval_help'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(43200)
                        ->required(),
                    Select::make('usage_refresh_unit')
                        ->label(__('settings.usage_refresh_unit'))
                        ->options(RefreshIntervalUnit::class)
                        ->native(false)
                        ->required(),
                ]),
                Toggle::make('customer_reminders_enabled')
                    ->label(__('settings.customer_reminders_enabled'))
                    ->helperText(__('settings.customer_reminders_enabled_help')),
                Section::make(__('settings.quote_section'))
                    ->schema([
                        TextInput::make('quote_validity_days')
                            ->label(__('settings.quote_validity_days'))
                            ->helperText(__('settings.quote_validity_days_help'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                        TextInput::make('quote_bcc_email')
                            ->label(__('settings.quote_bcc_email'))
                            ->helperText(__('settings.quote_bcc_email_help'))
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make(__('settings.company_section'))
                    ->description(__('settings.company_section_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label(__('settings.company_name'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('company_address')
                            ->label(__('settings.company_address'))
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('company_phone')
                            ->label(__('settings.company_phone'))
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('company_email')
                            ->label(__('settings.company_email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('company_tax_office')
                            ->label(__('settings.company_tax_office'))
                            ->maxLength(255),
                        TextInput::make('company_tax_number')
                            ->label(__('settings.company_tax_number'))
                            ->maxLength(255),
                        TextInput::make('company_iban')
                            ->label(__('settings.company_iban'))
                            ->helperText(__('settings.company_iban_help'))
                            ->maxLength(40)
                            ->columnSpanFull(),
                        FileUpload::make('company_logo')
                            ->label(__('settings.company_logo'))
                            ->helperText(__('settings.company_logo_help'))
                            ->disk('local')
                            ->directory(UpdateSettings::LOGO_DIRECTORY)
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif'])
                            ->maxSize(1024)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('sendTestEmail')
                            ->label(__('settings.send_test_email'))
                            ->color('gray')
                            ->action(fn () => $this->sendTestEmail()),
                        Action::make('save')
                            ->label(__('settings.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])
                        ->alignment(Alignment::End)
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function sendTestEmail(): void
    {
        $sendTestEmail = app(SendTestEmail::class);

        try {
            $recipient = $sendTestEmail->handle();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('settings.test_email_failed', ['message' => $sendTestEmail->describeFailure($exception)]))
                ->send();

            return;
        }

        if ($recipient === null) {
            Notification::make()
                ->warning()
                ->title(__('settings.test_email_no_address'))
                ->send();

            return;
        }

        $formAddress = trim((string) ($this->data['notification_email'] ?? ''));

        Notification::make()
            ->success()
            ->title(__('settings.test_email_sent', ['email' => $recipient]))
            ->body($formAddress !== $recipient ? __('settings.test_email_unsaved') : null)
            ->send();
    }

    public function save(UpdateSettings $updateSettings): void
    {
        $updateSettings->handle($this->form->getState());

        Notification::make()
            ->success()
            ->title(__('settings.saved'))
            ->send();
    }
}
