<?php

use App\Providers\Filament\AdminPanelProvider;

/**
 * The e-mail cannot read the panel's CSS variables, so its theme copies the panel's tokens
 * (docs/UI.md §6). This keeps every color of the copy equal to one of the panel's.
 */
it('uses only colors that the panel defines', function () {
    $panel = file_get_contents(resource_path('css/filament/panel/theme.css'));
    $tokenBlock = substr($panel, 0, (int) strpos($panel, 'Frame: sidebar'));
    preg_match_all('/#[0-9a-f]{6}\b/i', $tokenBlock, $tokenColors);
    $allowed = collect([
        ...$tokenColors[0],
        ...AdminPanelProvider::PRIMARY_SCALE,
        ...AdminPanelProvider::GRAY_SCALE,
    ])->map(fn (string $color): string => strtolower($color))->unique();

    $files = [
        resource_path('views/vendor/mail/html/themes/musteri.css'),
        resource_path('views/vendor/mail/html/layout.blade.php'),
    ];

    foreach ($files as $file) {
        preg_match_all('/#[0-9a-f]{3,8}\b/i', file_get_contents($file), $colors);

        expect($colors[0])->not->toBeEmpty();

        foreach ($colors[0] as $color) {
            expect($allowed->contains(strtolower($color)))->toBeTrue("{$color} in ".basename($file).' is not a panel color');
        }
    }
});
