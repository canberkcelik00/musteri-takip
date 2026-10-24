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

function deployStepRun(string $name): string
{
    return deploySteps()[deployStepIndex($name)]['run'];
}

it('builds the package only after the tests pass on a push to main', function () {
    $deploy = ciWorkflow()['jobs']['deploy'];

    expect((array) $deploy['needs'])->toBe(['tests'])
        ->and($deploy['if'])->toContain("github.event_name == 'push'")
        ->toContain("github.ref == 'refs/heads/main'")
        ->and($deploy['timeout-minutes'])->toBe(30)
        ->and($deploy['permissions'])->toBe(['contents' => 'write']);
});

it('runs Pint, Larastan and Pest', function () {
    $runs = collect(ciWorkflow()['jobs']['tests']['steps'])->pluck('run')->filter()->values()->all();

    expect($runs)->toContain('composer lint', 'composer analyse', 'composer test');
});

it('installs production dependencies with Composer scripts and an autoloader that does not list app classes', function () {
    expect(deployStepRun('Install production dependencies'))->toContain('composer install --no-dev')
        ->not->toContain('--no-scripts')
        ->not->toContain('--optimize-autoloader')
        ->not->toContain(' -o')
        ->and(deployStepRun('Build theme'))->toBe('npm run build');
});

it('checks that the package contains the dependencies and the theme', function () {
    expect(deployStepRun('Verify package'))->toContain('vendor/autoload.php')
        ->toContain('public/build/manifest.json')
        ->and(deployStepIndex('Verify package'))->toBeLessThan(deployStepIndex('Commit package if it changed'));
});

it('adds no commit when the package did not change', function () {
    $commit = deployStepRun('Commit package if it changed');
    $noChange = strpos($commit, 'if git diff --cached --quiet; then');

    expect($noChange)->toBeInt()
        ->and(strpos($commit, 'exit 0', $noChange))->toBeLessThan(strpos($commit, 'git commit'))
        ->and($commit)->toContain("if git diff --quiet -- vendor ':(exclude)vendor/composer/installed.php'; then")
        ->toContain('git checkout -- vendor/composer/installed.php');
});

it('leaves the package to the newer run when main moved on', function () {
    $commit = deployStepRun('Commit package if it changed');

    expect($commit)->toContain('if [ "$(git rev-parse FETCH_HEAD)" != "$GITHUB_SHA" ]; then')
        ->and(strpos($commit, 'git rev-parse FETCH_HEAD'))->toBeLessThan(strpos($commit, 'git add'));
});

it('says in the commit message what changed and which commit it was built for', function () {
    $commit = deployStepRun('Commit package if it changed');

    expect($commit)->toContain('changes+=("Composer bağımlılıkları (vendor) güncellendi")')
        ->toContain('changes+=("tema yeniden derlendi")')
        ->toContain('git commit -q -m "Yayın paketi: ${summary%, }" -m "Kaynak: ${GITHUB_SHA:0:8} ${source_subject}"')
        ->not->toContain('[skip ci]');
});

it('commits only the package and pushes without rewriting history', function () {
    $commit = deployStepRun('Commit package if it changed');

    expect($commit)->toContain('git add --all --force -- vendor public/build')
        ->toContain('fail "Paket dışında değişen dosyalar var')
        ->toContain('git push origin HEAD:refs/heads/main')
        ->not->toMatch('/push[^\n]*(--force|\s-f\b|\+HEAD)/')
        ->and(strpos($commit, 'Paket dışında değişen dosyalar var'))->toBeLessThan(strpos($commit, 'git commit'))
        ->and(array_key_last(deploySteps()))->toBe(deployStepIndex('Commit package if it changed'));
});

it('needs no secrets, no production branch and no cPanel deploy tasks', function () {
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));
    preg_match_all('/secrets\.([A-Z0-9_]+)/', $workflow, $matches);

    expect($matches[1])->toBe([])
        ->and($workflow)->not->toContain('refs/heads/production')
        ->and(file_exists(base_path('.cpanel.yml')))->toBeFalse()
        ->and(file_exists(base_path('RELEASE')))->toBeFalse();
});

it('keeps the package in git and the theme build independent of it', function () {
    $ignored = collect(file(base_path('.gitignore'), FILE_IGNORE_NEW_LINES))->map(fn (string $line): string => trim($line));

    expect($ignored)->not->toContain('/vendor')
        ->not->toContain('/public/build')
        ->toContain('.env')
        ->and(file_get_contents(resource_path('css/app.css')))->toContain("@import 'tailwindcss' source(none);");
});
