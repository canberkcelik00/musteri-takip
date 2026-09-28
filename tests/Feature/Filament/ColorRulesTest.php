<?php

use Illuminate\Support\Facades\File;

/**
 * Enforces the fixed status color rule (docs/UI.md §6): only danger, warning,
 * success and gray carry meaning; primary is for buttons and interactive
 * elements only. `info` is never used, and a boolean is never shown as a
 * colored icon — it is shown as text (docs/UI.md §2 rule 9).
 */
it('never uses the info color or an icon column boolean in app/Filament', function () {
    $forbidden = [
        "'info'",
        '->info(',
        'Color::Blue',
        'Color::Sky',
        'Color::Cyan',
    ];

    $files = [
        ...File::allFiles(app_path('Filament')),
        ...File::allFiles(app_path('Providers/Filament')),
    ];

    $violations = [];

    foreach ($files as $file) {
        $contents = $file->getContents();

        foreach ($forbidden as $needle) {
            if (str_contains($contents, $needle)) {
                $violations[] = $file->getFilename().': '.$needle;
            }
        }

        if (str_contains($contents, 'IconColumn') && str_contains($contents, '->boolean(')) {
            $violations[] = $file->getFilename().': IconColumn with boolean()';
        }
    }

    expect($violations)->toBe([]);
});
