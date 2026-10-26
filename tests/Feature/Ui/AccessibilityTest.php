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

// Table hierarchy (G-11.6).

it('sets the column heads apart and keeps them in view on a wide screen with a pointer', function () {
    $tables = uiStylesheets()['tables'];

    preg_match('/^\.ui-table th\s*\{([^{}]*)\}/m', $tables, $head);
    expect($head[1])->toContain('letter-spacing')->toContain('border-bottom: 2px solid var(--mt-divider)');

    expect($tables)
        ->toContain('@media (min-width: 768px) and (hover: hover)')
        ->toContain('position: sticky')
        ->toContain('max-height: 70dvh');
});

it('lets a wide table scroll inside its card and not chain the scroll to the page', function () {
    preg_match('/\.ui-table-wrap\s*\{([^{}]*)\}/', uiStylesheets()['tables'], $wrap);

    expect($wrap[1])->toContain('overflow-x: auto')->toContain('overscroll-behavior-x: contain')->toContain('max-width: 100%');
});

it('shows hover on real pointers only and draws a selected row', function () {
    $tables = uiStylesheets()['tables'];

    expect($tables)
        ->toContain('@media (hover: hover) {'."\n".'    .ui-row:hover')
        ->toContain('.ui-row:focus-within')
        ->toContain(".ui-row[aria-selected='true']")
        ->toContain('.ui-row.is-selected');
});

it('sizes a name above its detail and sets numbers apart by weight', function () {
    $tables = uiStylesheets()['tables'];

    preg_match('/\.ui-cell-title\s*\{([^{}]*)\}/', $tables, $title);

    expect($title[1])->toContain('font-size: var(--mt-text-md)')
        ->and($tables)->toContain('.ui-table td.is-end')->toContain('.ui-table tfoot td.is-end');
});
