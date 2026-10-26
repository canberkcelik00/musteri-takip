<?php

it('preloads the latin Inter subset in the document head', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('rel="preload" as="font" type="font/woff2" crossorigin', false);
});

it('keeps the preloaded font in the built manifest so the real page resolves it', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

    expect($manifest)->toHaveKey('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2');
});

it('does not bundle an unused web font', function () {
    expect(file_get_contents(base_path('vite.config.js')))->not->toContain('bunny(');
});

it('prefetches sidebar and tab bar pages on hover', function () {
    foreach ([
        'resources/views/components/ui/nav-item.blade.php',
        'resources/views/layouts/app.blade.php',
    ] as $view) {
        $source = file_get_contents(base_path($view));

        expect($source)->toContain('wire:navigate.hover')
            ->and($source)->not->toMatch('/wire:navigate(?!\.hover)/');
    }
});

it('paints the page gradient on a fixed layer without a blur', function () {
    $css = file_get_contents(base_path('resources/css/ui/base.css'));

    preg_match('/body::before\s*\{[^}]*\}/', $css, $layer);

    expect($layer)->not->toBeEmpty()
        ->and($layer[0])->toContain('position: fixed')
        ->and($layer[0])->not->toContain('backdrop-filter');
});
