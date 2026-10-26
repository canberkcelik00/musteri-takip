<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

// Shared components that replace hand-written markup (G-11.7).

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

it('draws the page header with its subtitle and its actions', function () {
    $html = Blade::render('<x-ui.page-header title="Müşteriler" subtitle="Alt başlık"><button>Ekle</button></x-ui.page-header>');

    expect($html)->toContain('class="ui-page-head"')->toContain('<h1 class="ui-title">Müşteriler</h1>')
        ->toContain('class="ui-subtitle">Alt başlık')->toContain('<button>Ekle</button>');
    expect(Blade::render('<x-ui.page-header title="Ödemeler" />'))->not->toContain('ui-subtitle');
});

it('draws a link button as a button, or as a link with an address', function () {
    expect(Blade::render('<x-ui.link-button x-on:click="go()">Ad</x-ui.link-button>'))
        ->toContain('<button type="button"')->toContain('class="ui-link-button"');

    $link = Blade::render('<x-ui.link-button href="/x" :danger="true" :block="true">Ad</x-ui.link-button>');

    expect($link)->toContain('<a href="/x"')->toContain('ui-link-button is-danger is-block');
});

it('draws the checkbox, the radio group and the file field', function () {
    expect(Blade::render('<x-ui.checkbox class="is-full" wire:model="remember" label="Beni hatırla" />'))
        ->toContain('class="ui-check is-full"')->toContain('type="checkbox"')->toContain('wire:model="remember"')->toContain('Beni hatırla');

    $radio = Blade::render('<x-ui.radio wire:model="first" :options="[1 => \'Evet\', 0 => \'Hayır\']" />');

    expect($radio)->toContain('class="ui-radio"')->toContain('value="1"')->toContain('value="0"')->toContain('Hayır');
    expect(substr_count($radio, 'wire:model="first"'))->toBe(2);

    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['logo' => ['Çok büyük']])));

    expect(Blade::render('<x-ui.file-input label="Logo" hint="PNG" wire:model="logo" />'))
        ->toContain('type="file"')->toContain('for="f-logo"')->toContain('class="ui-hint">PNG')->toContain('class="ui-error"');
});

it('gives a select a minimum width without an inline style', function () {
    $html = Blade::render('<x-ui.select width="md" wire:model="x" :options="[\'a\' => \'A\']" />');

    expect($html)->toContain('ui-field ui-width-md')->not->toContain('style="min-width');
});

it('marks a required field with a star beside its label', function () {
    expect(Blade::render('<x-ui.input label="Ad" wire:model="name" required />'))->toContain('class="ui-required"');
    expect(Blade::render('<x-ui.input label="Ad" wire:model="name" />'))->not->toContain('ui-required');
});

it('shows an empty state with an icon, and the icon can be swapped', function () {
    $html = Blade::render('<x-ui.empty-state heading="Boş" description="Ekle" />');

    expect($html)->toContain('class="ui-empty"')->toContain('ui-empty-icon')->toContain('<h2>Boş</h2>');
    expect(Blade::render('<x-ui.empty-state heading="Yok" icon="document-magnifying-glass" />'))->toContain('ui-empty-icon');
});

it('leaves no one-off inline style in the screens but the progress bar width', function () {
    $offenders = [];

    foreach (['livewire', 'components/ui'] as $folder) {
        foreach (File::allFiles(resource_path("views/{$folder}")) as $file) {
            foreach (file($file->getPathname()) as $number => $line) {
                if (str_contains($line, ' style="') && ! str_contains($line, 'ui-bar-fill')) {
                    $offenders[] = $file->getRelativePathname().':'.($number + 1);
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('uses the shared page header and link button instead of hand-written markup', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views/livewire')) as $file) {
        $source = (string) file_get_contents($file->getPathname());

        if (str_contains($source, '<h1 class="ui-title"') || str_contains($source, '<button type="button" class="ui-link-button') || str_contains($source, '<table')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('draws a card on the tag it is given, flush or padded', function () {
    $section = Blade::render('<x-ui.card tag="section" flush class="ui-mt-5">x</x-ui.card>');
    $form = Blade::render('<x-ui.card tag="form" wire:submit="save">x</x-ui.card>');

    expect($section)->toContain('<section')->toContain('ui-card')->toContain('ui-pane')->toContain('is-flush')->toContain('ui-mt-5')
        ->and($form)->toContain('<form')->toContain('wire:submit="save"')->not->toContain('is-flush')
        ->and(Blade::render('<x-ui.card>x</x-ui.card>'))->toContain('<div');
});

it('renders the search field with its slot and an extra wrapper class', function () {
    $html = Blade::render('<x-ui.search-field wrapper-class="ui-search-field" placeholder="Ara"><p>sonuç</p></x-ui.search-field>');

    expect($html)->toContain('class="ui-search-input ui-search-field"')->toContain('<p>sonuç</p>')->toContain('x-ref="field"');
});

it('keeps the glass card pair in the card and table components, not in the screens', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views/livewire')) as $file) {
        if (str_contains((string) file_get_contents($file->getPathname()), 'ui-card ui-pane')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
