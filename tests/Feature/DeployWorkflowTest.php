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
    $jobs = ciWorkflow()['jobs'];

    foreach (['deploy-settings', 'deploy'] as $job) {
        expect((array) $jobs[$job]['needs'])->toContain('tests')
            ->and($jobs[$job]['if'])->toContain("github.event_name == 'push'")
            ->toContain("github.ref == 'refs/heads/main'");
    }

    expect($jobs['deploy']['needs'])->toContain('deploy-settings')
        ->and($jobs['deploy']['if'])->toContain("needs.deploy-settings.outputs.enabled == 'true'");
});

it('never cancels a deploy halfway', function () {
    $workflow = ciWorkflow();

    expect($workflow['concurrency']['cancel-in-progress'])->toBe("\${{ github.ref != 'refs/heads/main' }}")
        ->and($workflow['jobs']['deploy']['concurrency'])->toBe(['group' => 'deploy', 'cancel-in-progress' => false]);
});

it('limits how long a deploy and its endpoint call may run', function () {
    expect(ciWorkflow()['jobs']['deploy']['timeout-minutes'])->toBe(90)
        ->and(deployStep('Call deploy endpoint')['timeout-minutes'])->toBe(20);
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

it('checks that the package contains the theme and the Filament assets', function () {
    expect(deployStep('Verify package')['run'])->toContain('dist/public/build/manifest.json')
        ->toContain('public/js/filament')
        ->toContain('public/css/filament')
        ->toContain('public/fonts/filament');
});

it('uploads the files, then RELEASE, then calls the deploy endpoint', function () {
    $upload = deployStep('Upload files');
    $release = deployStep('Remove stale caches and upload RELEASE')['run'];
    $call = deployStep('Call deploy endpoint')['run'];

    expect($upload['uses'])->toMatch('/^SamKirkland\/FTP-Deploy-Action@[0-9a-f]{40}$/')
        ->and($upload['with']['protocol'])->toBe('ftps')
        ->and(explode("\n", trim($upload['with']['exclude'])))->toContain('.env', 'RELEASE')
        ->and($release)->toContain('mrm bootstrap/cache/*.php')
        ->toContain('rm -rf bootstrap/cache/filament')
        ->toContain('set ssl:verify-certificate yes')
        ->and(strrpos($release, 'put RELEASE'))->toBeGreaterThan(strrpos($release, 'set cmd:fail-exit yes'))
        ->and($call)->toContain('Authorization: Bearer $DEPLOY_KEY')
        ->toContain('"409"')
        ->not->toContain('set -x')
        ->and(deployStepIndex('Verify package'))->toBeLessThan(deployStepIndex('Upload files'))
        ->and(deployStepIndex('Upload files'))->toBeLessThan(deployStepIndex('Remove stale caches and upload RELEASE'))
        ->and(deployStepIndex('Remove stale caches and upload RELEASE'))->toBeLessThan(deployStepIndex('Call deploy endpoint'));
});

it('documents and checks every secret the workflow uses', function () {
    preg_match_all('/secrets\.([A-Z0-9_]+)/', file_get_contents(base_path('.github/workflows/ci.yml')), $matches);
    $secrets = collect($matches[1])->unique()->reject(fn (string $name): bool => $name === 'GITHUB_TOKEN');

    $readme = file_get_contents(base_path('README.md'));
    $deploySection = str($readme)->after('## Yayına alma')->before("\n## ")->toString();
    $settingsCheck = ciWorkflow()['jobs']['deploy-settings']['steps'][0]['run'];

    expect($secrets)->not->toBeEmpty();

    foreach ($secrets as $secret) {
        expect($deploySection)->toContain("`{$secret}`")
            ->and($settingsCheck)->toContain($secret);
    }
});
