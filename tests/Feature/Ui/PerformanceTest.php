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
