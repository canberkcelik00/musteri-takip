<?php

use App\Providers\AppServiceProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Validator;

it('uses Turkish as the application locale', function () {
    expect(app()->getLocale())->toBe('tr');
});

it('uses the Europe/Istanbul timezone', function () {
    expect(config('app.timezone'))->toBe('Europe/Istanbul')
        ->and(now()->getTimezone()->getName())->toBe('Europe/Istanbul');
});

it('shows validation messages in Turkish', function () {
    $validator = Validator::make(['email' => ''], ['email' => 'required']);

    expect($validator->errors()->first('email'))->toBe('e-posta adresi alanı zorunludur.');
});

it('displays dates as dd.mm.yyyy in Filament', function () {
    expect(AppServiceProvider::DATE_FORMAT)->toBe('d.m.Y')
        ->and(DatePicker::make('starts_on')->getDisplayFormat())->toBe('d.m.Y')
        ->and(DatePicker::make('starts_on')->isNative())->toBeFalse()
        ->and(Table::make(Mockery::mock(HasTable::class))->getDefaultDateDisplayFormat())->toBe('d.m.Y')
        ->and(Schema::make()->getDefaultDateDisplayFormat())->toBe('d.m.Y');
});
