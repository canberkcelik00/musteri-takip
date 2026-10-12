<?php

function releaseScript(): string
{
    return file_get_contents(base_path('yayin.sh'));
}

/**
 * Position of a fragment in the release script; fails the test when it is missing.
 */
function releaseScriptPosition(string $fragment): int
{
    $position = strpos(releaseScript(), $fragment);

    expect($position)->toBeInt("[{$fragment}] is missing from yayin.sh.");

    return $position;
}

it('stops on another branch or with uncommitted changes, before doing anything', function () {
    $branchCheck = releaseScriptPosition('[ "$(git branch --show-current)" = "main" ] || fail');
    $cleanCheck = releaseScriptPosition('[ -z "$(git status --porcelain)" ] || fail');

    expect(releaseScript())->toStartWith("#!/usr/bin/env bash\n")
        ->toContain('set -euo pipefail')
        ->and($branchCheck)->toBeLessThan(releaseScriptPosition('composer test'))
        ->and($cleanCheck)->toBeLessThan(releaseScriptPosition('composer test'));
});

it('builds nothing unless the tests, the code style and the static analysis pass', function () {
    $install = releaseScriptPosition('composer install --no-dev');

    expect(releaseScript())->toContain('composer test || fail')
        ->toContain('composer lint || fail')
        ->toContain('composer analyse || fail')
        ->and(releaseScriptPosition('composer test'))->toBeLessThan($install)
        ->and(releaseScriptPosition('composer lint'))->toBeLessThan($install)
        ->and(releaseScriptPosition('composer analyse'))->toBeLessThan($install);
});

it('installs vendor without dev packages in a throwaway container, keeping Composer scripts', function () {
    $line = str(releaseScript())->after('docker run --rm')->before('|| fail')->squish()->toString();

    expect($line)->toContain('composer install --no-dev --optimize-autoloader')
        ->not->toContain('--no-scripts')
        ->and(releaseScript())->not->toContain('exec -T app composer install')
        ->and(releaseScriptPosition('php artisan package:discover'))->toBeGreaterThan(releaseScriptPosition('composer install --no-dev'));
});

it('commits only the package, with the source commit as the release id', function () {
    $commit = releaseScriptPosition('git commit');

    expect(releaseScript())->toContain('package_paths=(vendor public/build public/js/filament public/css/filament public/fonts/filament RELEASE)')
        ->toContain('source_commit="$(git rev-parse HEAD)"')
        ->toContain('git add --all --force -- "${package_paths[@]}"')
        ->and(releaseScriptPosition('source_commit="$(git rev-parse HEAD)"'))->toBeLessThan(releaseScriptPosition('npm run build'))
        ->and(releaseScriptPosition('printf \'%s\' "$source_commit" > RELEASE'))->toBeLessThan($commit)
        ->and(releaseScriptPosition('Paket dışında değişen dosyalar var'))->toBeLessThan($commit);
});

it('pushes main without rewriting history and without skipping CI', function () {
    expect(releaseScript())->toContain('git push origin main')
        ->not->toMatch('/push[^\n]*(--force|\s-f\b|\+)/')
        ->not->toContain('[skip ci]')
        ->and(releaseScriptPosition('git push origin main'))->toBeGreaterThan(releaseScriptPosition('git commit'));
});

it('keeps the package in git like any other file', function () {
    $ignored = collect(file(base_path('.gitignore'), FILE_IGNORE_NEW_LINES))->map(fn (string $line): string => trim($line));

    expect($ignored)->not->toContain('/vendor')
        ->not->toContain('/public/build')
        ->not->toContain('/public/js/filament')
        ->not->toContain('/public/css/filament')
        ->not->toContain('/public/fonts/filament')
        ->not->toContain('/RELEASE')
        ->toContain('.env');
});
