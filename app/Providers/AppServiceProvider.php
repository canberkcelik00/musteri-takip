<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const DATE_FORMAT = 'd.m.Y';

    public const DATE_TIME_FORMAT = 'd.m.Y H:i';

    public const DATE_TIME_WITH_SECONDS_FORMAT = 'd.m.Y H:i:s';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureFilamentDateFormats();
        $this->configureFilamentActions();
    }

    /**
     * Form panels end in "Vazgeç" on the left and "Kaydet" on the right, bottom right (docs/UI.md
     * §5). Filament's "end" alignment already renders the footer reversed, so the default order
     * (submit, cancel) shows as Vazgeç · Kaydet; confirmation modals keep Filament's centered
     * layout. "Vazgeç" replaces Filament's "İptal", which reads like the red "İptal et" actions.
     * A form panel's submit button is "Kaydet" (not Filament's "Gönder") unless the action names
     * its own verb ("Böl", "İptal et") or asks for a confirmation. Every modal that holds a form
     * opens as a slide-over (docs/UI.md §2 Kural 8) — also one opened from inside another panel;
     * a plain confirmation stays a small centered dialog.
     */
    private function configureFilamentActions(): void
    {
        Action::configureUsing(fn (Action $action): Action => $action
            ->modalCancelActionLabel(__('actions.discard'))
            ->modalSubmitActionLabel(fn (Action $action): ?string => $action->isConfirmationRequired() ? null : __('actions.save'))
            ->slideOver(fn (Action $action): bool => $action->hasFormWrapper() && ! $action->isConfirmationRequired())
            ->modalFooterActionsAlignment(fn (Action $action): ?Alignment => $action->isConfirmationRequired() ? null : Alignment::End));

        CreateAction::configureUsing(fn (CreateAction $action): CreateAction => $action
            ->createAnother(false)
            ->modalSubmitActionLabel(__('actions.save')));
    }

    /**
     * Display dates as dd.mm.yyyy across Filament tables, schemas and pickers.
     */
    private function configureFilamentDateFormats(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->native(false)
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT)
            ->defaultDateTimeWithSecondsDisplayFormat(self::DATE_TIME_WITH_SECONDS_FORMAT));
    }
}
