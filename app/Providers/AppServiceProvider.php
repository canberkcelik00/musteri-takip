<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const DATE_FORMAT = 'd.m.Y';

    public const DATE_TIME_FORMAT = 'd.m.Y H:i';

    public const DATE_TIME_WITH_SECONDS_FORMAT = 'd.m.Y H:i:s';

    /**
     * Filament writes a string modal width onto the modal window as a class; the theme gives
     * this one the shared form window width and look.
     */
    public const FORM_WINDOW_CLASS = 'fi-mt-form-modal';

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
     * Form windows end in "Vazgeç" on the left and "Kaydet" on the right, bottom right (docs/UI.md
     * §5). Filament's "end" alignment already renders the footer reversed, so the default order
     * (submit, cancel) shows as Vazgeç · Kaydet; confirmation modals keep Filament's centered
     * layout. "Vazgeç" replaces Filament's "İptal", which reads like the red "İptal et" actions.
     * A form window's submit button is "Kaydet" (not Filament's "Gönder") unless the action names
     * its own verb ("Böl", "İptal et") or asks for a confirmation. Every modal that holds a form
     * opens as the centered form window (docs/UI.md §2 Kural 8, T-84) — also one opened from inside
     * another window: one shared width (the theme's `--mt-modal-width`, through the window class)
     * and a sticky header and footer, so a long form scrolls only its fields. A plain confirmation
     * stays Filament's small centered dialog.
     */
    private function configureFilamentActions(): void
    {
        Action::configureUsing(fn (Action $action): Action => $action
            ->modalCancelActionLabel(__('actions.discard'))
            ->modalSubmitActionLabel(fn (Action $action): ?string => $action->isConfirmationRequired() ? null : __('actions.save'))
            ->modalWidth(fn (Action $action): ?string => self::isFormWindow($action) ? self::FORM_WINDOW_CLASS : null)
            ->stickyModalHeader(fn (Action $action): bool => self::isFormWindow($action))
            ->stickyModalFooter(fn (Action $action): bool => self::isFormWindow($action))
            ->modalFooterActionsAlignment(fn (Action $action): ?Alignment => $action->isConfirmationRequired() ? null : Alignment::End));

        CreateAction::configureUsing(fn (CreateAction $action): CreateAction => $action
            ->createAnother(false)
            ->modalSubmitActionLabel(__('actions.save')));
    }

    private static function isFormWindow(Action $action): bool
    {
        return $action->hasFormWrapper() && ! $action->isConfirmationRequired();
    }

    /**
     * Display dates as dd.mm.yyyy across Filament tables, schemas and pickers.
     */
    private function configureFilamentDateFormats(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT)
            // The filter panel's "Uygula" is secondary: the one primary button of a screen is the top bar's "Ödeme al" (G-4B.5).
            ->filtersApplyAction(fn (Action $action): Action => $action->color('gray'))
            // Filters open in a centered window like the forms (T-84); without this Filament shows them in a dropdown.
            ->filtersLayout(FiltersLayout::Modal));

        // One column unless a schema asks for more (docs/UI.md §5, T-84): ListRecords and relation
        // managers otherwise give a form without its own columns a two-column grid from `lg`, where
        // a Section takes only one of the two. A component's child schema takes its columns from the
        // component, so `Section::make()->columns(2)` keeps its two columns.
        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->columns(1)
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->native(false)
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT)
            ->defaultDateTimeWithSecondsDisplayFormat(self::DATE_TIME_WITH_SECONDS_FORMAT));
    }
}
