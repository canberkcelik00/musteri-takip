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
        ->and(deployStepIndex('Build theme'))->toBeLessThan(deployStepIndex('Verify package'));
});

it('checks that the package contains the dependencies, the theme and the Filament assets', function () {
    expect(deployStep('Verify package')['run'])->toContain('vendor/autoload.php')
        ->toContain('public/build/manifest.json')
        ->toContain('public/js/filament')
        ->toContain('public/css/filament')
        ->toContain('public/fonts/filament');
});

it('commits the verified package to main without rewriting its history or starting another run', function () {
    $commit = deployStep('Commit package to main')['run'];

    expect($commit)->toContain('printf \'%s\' "$GITHUB_SHA" > RELEASE')
        ->toContain('git add --all --force vendor public/build public/js/filament public/css/filament public/fonts/filament RELEASE')
        ->toContain('git commit -q -m "Paket $GITHUB_SHA [skip ci]"')
        ->toContain('git push origin HEAD:refs/heads/main')
        ->not->toMatch('/push[^\n]*(--force|\s-f\b|\+HEAD)/')
        ->and(deployStepIndex('Verify package'))->toBeLessThan(deployStepIndex('Commit package to main'))
        ->and(array_key_last(deploySteps()))->toBe(deployStepIndex('Commit package to main'));
});

it('leaves the package to the newer run when main moved on', function () {
    $commit = deployStep('Commit package to main')['run'];

    expect($commit)->toContain('git fetch --depth=1 origin main')
        ->toContain('if [ "$(git rev-parse FETCH_HEAD)" != "$GITHUB_SHA" ]; then')
        ->and(strpos($commit, 'exit 0'))->toBeLessThan(strpos($commit, 'git add'));
});

it('never commits .env, storage content or caches', function () {
    $commit = deployStep('Commit package to main')['run'];

    expect($commit)->toContain("grep -Ev '^(vendor/|public/build/|public/(js|css|fonts)/filament/|RELEASE$)'")
        ->toContain('fail "Pakette beklenmeyen dosyalar var')
        ->and(strpos($commit, 'git diff --cached --name-only'))->toBeLessThan(strpos($commit, 'git commit'))
        ->and(str($commit)->after('git add')->before("\n")->toString())->not->toContain('.env')
        ->not->toContain('storage')
        ->not->toContain('bootstrap');
});

it('needs no secrets, no production branch and no cPanel deploy tasks', function () {
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));
    preg_match_all('/secrets\.([A-Z0-9_]+)/', $workflow, $matches);

    expect($matches[1])->toBe([])
        ->and($workflow)->not->toContain('refs/heads/production')
        ->and(ciWorkflow()['jobs'])->not->toHaveKey('deploy-settings')
        ->and(collect(deploySteps())->pluck('uses')->filter()->implode(' '))->not->toContain('FTP-Deploy-Action')
        ->and(file_exists(base_path('.cpanel.yml')))->toBeFalse();
});
