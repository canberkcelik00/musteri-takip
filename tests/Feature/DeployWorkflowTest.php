<?php

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function ciWorkflow(): array
{
    return Yaml::parseFile(base_path('.github/workflows/ci.yml'));
}

it('only runs the tests: the release is built and pushed by yayin.sh', function () {
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

    expect(array_keys(ciWorkflow()['jobs']))->toBe(['tests'])
        ->and($workflow)->not->toContain('git push')
        ->not->toContain('contents: write')
        ->not->toContain('--no-dev');
});

it('runs Pint, Larastan and Pest', function () {
    $runs = collect(ciWorkflow()['jobs']['tests']['steps'])->pluck('run')->filter()->values()->all();

    expect($runs)->toContain('composer lint', 'composer analyse', 'composer test');
});

it('needs no secrets, no production branch and no cPanel deploy tasks', function () {
    preg_match_all('/secrets\.([A-Z0-9_]+)/', file_get_contents(base_path('.github/workflows/ci.yml')), $matches);

    expect($matches[1])->toBe([])
        ->and(file_get_contents(base_path('.github/workflows/ci.yml')))->not->toContain('production')
        ->and(file_exists(base_path('.cpanel.yml')))->toBeFalse();
});
