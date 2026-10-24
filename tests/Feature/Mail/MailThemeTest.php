<?php

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\View;

/**
 * The e-mail cannot read the interface's CSS variables, so its theme copies the glass tokens
 * (docs/UI.md §6). This keeps every color of the copy equal to one of the tokens' colors.
 */
it('uses only colors that the interface defines', function () {
    $tokens = uiStylesheets()['tokens'];
    preg_match_all('/#[0-9a-f]{6}\b/i', $tokens, $tokenColors);
    $allowed = collect($tokenColors[0])->map(fn (string $color): string => strtolower($color))->unique();

    $files = [
        resource_path('views/vendor/mail/html/themes/musteri.css'),
        resource_path('views/vendor/mail/html/layout.blade.php'),
    ];

    foreach ($files as $file) {
        preg_match_all('/#[0-9a-f]{3,8}\b/i', file_get_contents($file), $colors);

        expect($colors[0])->not->toBeEmpty();

        foreach ($colors[0] as $color) {
            expect($allowed->contains(strtolower($color)))->toBeTrue("{$color} in ".basename($file).' is not an interface color');
        }
    }
});

it('still parses the body of an ordinary Markdown mail', function () {
    View::addLocation(base_path('tests/Fixtures/views'));

    $mailable = new class extends Mailable
    {
        public function build(): static
        {
            return $this->markdown('markdown-sample');
        }
    };

    $html = $mailable->render();

    expect($html)->toContain('<strong>kalın</strong>')
        ->and($html)->toContain('<li>birinci</li>')
        ->and($html)->toContain('<li>ikinci</li>')
        ->and($html)->toContain('Tüm hakları saklıdır.');
});
