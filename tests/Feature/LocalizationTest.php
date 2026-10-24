<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;

it('uses Turkish as the application locale', function () {
    expect(app()->getLocale())->toBe('tr');
});

it('uses the Europe/Istanbul timezone', function () {
    expect(config('app.timezone'))->toBe('Europe/Istanbul')
        ->and(now()->getTimezone()->getName())->toBe('Europe/Istanbul');
});

it('shows validation messages in Turkish', function () {
    $validator = Validator::make(['email' => ''], ['email' => 'required']);

    expect($validator->errors()->first('email'))->toBe('e-posta adresi boş bırakılamaz; bir değer yazın veya seçin.');
});

it('displays dates as dd.mm.yyyy in the glass interface', function () {
    view()->share('errors', new ViewErrorBag);

    expect(Blade::render('<x-ui.date-input label="Tarih" wire:model="date" />'))->toContain('gg.aa.yyyy');
});
