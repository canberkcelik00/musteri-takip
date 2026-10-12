<?php

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function ciWorkflow(): array
{
    return Yaml::parseFile(base_path('.github/workflows/ci.yml'));
}

/**
 * @return array<int, array<string, mixed>>
 */
function deploySteps(): array
{
    return ciWorkflow()['jobs']['deploy']['steps'];
}

function deployStepIndex(string $name): int
{
    $index = collect(deploySteps())->search(fn (array $step): bool => ($step['name'] ?? null) === $name);

    expect($index)->toBeInt("Deploy step [{$name}] is missing.");

    return $index;
}

/**
 * @return array<string, mixed>
 */
function deployStep(string $name): array
{
    return deploySteps()[deployStepIndex($name)];
}

it('deploys only after the tests pass on a push to main', function () {
    $deploy = ciWorkflow()['jobs']['deploy'];

    expect((array) $deploy['needs'])->toBe(['tests'])
        ->and($deploy['if'])->toContain("github.event_name == 'push'")
        ->toContain("github.ref == 'refs/heads/main'");
});

it('never cancels a deploy halfway', function () {
    $workflow = ciWorkflow();

    expect($workflow['concurrency']['cancel-in-progress'])->toBe("\${{ github.ref != 'refs/heads/main' }}")
        ->and($workflow['jobs']['deploy']['concurrency'])->toBe(['group' => 'deploy', 'cancel-in-progress' => false]);
});

it('limits how long a deploy may run and lets it write only repository contents', function () {
    $deploy = ciWorkflow()['jobs']['deploy'];

    expect($deploy['timeout-minutes'])->toBe(30)
        ->and($deploy['permissions'])->toBe(['contents' => 'write']);
});

it('builds the package with production dependencies, Composer scripts and the theme', function () {
    $install = deployStep('Install production dependencies')['run'];

    expect($install)->toContain('composer install')
        ->toContain('--no-dev')
        ->not->toContain('--no-scripts')
        ->and(deployStep('Build theme')['run'])->toBe('npm run build')
        ->and(deployStepIndex('Build theme'))->toBeLessThan(deployStepIndex('Prepare package'));
});

it('keeps .env, storage content and caches out of the package', function () {
    $prepare = deployStep('Prepare package')['run'];
    $verify = deployStep('Verify package')['run'];

    expect($prepare)->not->toContain('.env')
        ->toContain("--exclude='/storage/**'")
        ->toContain("--exclude='/bootstrap/cache/*'")
        ->and($verify)->toContain('dist/.env')
        ->toContain('dist/RELEASE')
        ->toContain('dist/bootstrap/cache')
        ->toContain('dist/storage');
});

it('checks that the package contains the theme, the Filament assets and the cPanel tasks', function () {
    expect(deployStep('Prepare package')['run'])->toContain('.cpanel.yml')
        ->and(deployStep('Verify package')['run'])->toContain('dist/public/build/manifest.json')
        ->toContain('public/js/filament')
        ->toContain('public/css/filament')
        ->toContain('public/fonts/filament')
        ->toContain('dist/.cpanel.yml');
});

it('publishes the verified package to the production branch without rewriting its history', function () {
    $publish = deployStep('Publish to production branch')['run'];

    expect($publish)->toContain('printf \'%s\' "$GITHUB_SHA" > dist/RELEASE')
        ->toContain('rsync -a --delete')
        ->toContain('git -C release push origin HEAD:refs/heads/production')
        ->not->toMatch('/push[^\n]*(--force|\s-f\b|\+HEAD)/')
        ->and(deployStepIndex('Verify package'))->toBeLessThan(deployStepIndex('Publish to production branch'))
        ->and(array_key_last(deploySteps()))->toBe(deployStepIndex('Publish to production branch'));
});

it('never commits files that are created on the hosting', function () {
    $publish = deployStep('Publish to production branch')['run'];
    $gitignore = str($publish)->after("<<'GITIGNORE'")->before('GITIGNORE')->explode("\n")
        ->map(fn (string $line): string => trim($line))
        ->filter()
        ->values()
        ->all();

    expect($gitignore)->toContain('/.env', '/.htaccess', 'error_log', '/public/storage');
});

it('needs no secrets', function () {
    preg_match_all('/secrets\.([A-Z0-9_]+)/', file_get_contents(base_path('.github/workflows/ci.yml')), $matches);

    expect($matches[1])->toBe([])
        ->and(ciWorkflow()['jobs'])->not->toHaveKey('deploy-settings')
        ->and(collect(deploySteps())->pluck('uses')->filter()->implode(' '))->not->toContain('FTP-Deploy-Action');
});

it('clears stale caches before cPanel runs the deploy command', function () {
    $tasks = Yaml::parseFile(base_path('.cpanel.yml'))['deployment']['tasks'];

    expect($tasks)->toBe([
        '/bin/rm -f bootstrap/cache/*.php',
        '/bin/rm -rf bootstrap/cache/filament',
        '/opt/cpanel/ea-php83/root/usr/bin/php artisan app:deploy',
    ]);
});
