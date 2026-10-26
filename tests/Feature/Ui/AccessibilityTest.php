<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

// Focus, field edges, motion, touch targets and error marks (G-11.5).

it('draws the focus outline with a solid token, not the translucent halo', function () {
    $base = uiStylesheets()['base'];

    preg_match('/:focus-visible\s*\{([^{}]*)\}/', $base, $focus);

    expect($focus[1])->toContain('var(--mt-focus-edge)')->not->toContain('var(--mt-focus-ring)');
    expect(uiStylesheets()['controls'])->toContain('.ui-input:focus-visible')->toContain('.ui-select-trigger:focus-visible');
});

it('gives the text fields and the select a visible edge', function () {
    $controls = uiStylesheets()['controls'];

    foreach (['.ui-input', '.ui-select-trigger'] as $selector) {
        preg_match('/^'.preg_quote($selector, '/').'\s*\{([^{}]*)\}/m', $controls, $rule);

        expect($rule[1])->toContain('border: 1px solid var(--mt-input-border)');
    }
});

it('stops animations and transitions when the system asks for less motion', function () {
    expect(uiStylesheets()['base'])
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('transition-duration: 0.01ms');
});

it('grows the hit area of the small controls on touch screens', function () {
    $base = uiStylesheets()['base'];

    expect($base)->toContain('@media (pointer: coarse)');

    foreach (['.ui-btn-sm', '.ui-chip-x', '.ui-date-nav', '.ui-icon-link', '.ui-search-clear'] as $selector) {
        expect($base)->toContain("{$selector}::before");
    }
});

it('keeps the tooltip inside the screen', function () {
    preg_match('/\.ui-tooltip::after\s*\{([^{}]*)\}/', uiStylesheets()['tables'], $tooltip);

    expect($tooltip[1])->toContain('max-width: min(240px, calc(100vw - 32px))')->toContain('white-space: normal');
});

it('marks an error message with a "!" mark besides its color', function () {
    expect(uiStylesheets()['controls'])->toContain('.ui-error::before');

    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['name' => ['Zorunlu']])));

    expect(Blade::render('<x-ui.input label="Ad" wire:model="name" />'))->toContain('class="ui-error"');
});
