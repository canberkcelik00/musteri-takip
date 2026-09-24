<?php

namespace App\Providers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
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
