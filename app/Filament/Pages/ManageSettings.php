<?php

namespace App\Filament\Pages;

use App\Actions\Settings\UpdateSettings;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

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
            'digest_time' => $settings->digestTime(),
            'notification_email' => $settings->notificationEmail(),
        ]);
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

    public function save(UpdateSettings $updateSettings): void
    {
        $updateSettings->handle($this->form->getState());

        Notification::make()
            ->success()
            ->title(__('settings.saved'))
            ->send();
    }
}
