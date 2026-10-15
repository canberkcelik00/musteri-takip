<?php

namespace App\Filament\Pages;

use App\Actions\Settings\SendTestEmail;
use App\Actions\Settings\UpdateSettings;
use App\Enums\DigestFrequency;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
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
        $this->form->fill([
            'renewal_lead_days' => $settings->renewalLeadDays(),
            'digest_frequency' => $settings->digestFrequency(),
            'digest_day_of_month' => $settings->digestDayOfMonth(),
            'digest_time' => $settings->digestTime(),
            'notification_email' => $settings->notificationEmail(),
            'customer_reminders_enabled' => $settings->customerRemindersEnabled(),
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
                Toggle::make('customer_reminders_enabled')
                    ->label(__('settings.customer_reminders_enabled'))
                    ->helperText(__('settings.customer_reminders_enabled_help')),
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
