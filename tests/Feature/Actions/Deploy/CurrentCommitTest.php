<?php

use App\Actions\Deploy\CurrentCommit;

afterEach(fn () => cleanUpDeploy());

function currentCommit(): ?string
{
    return (new CurrentCommit(deployTestDirectory().'/.git'))->id();
}

it('reads the commit of the checked-out branch', function () {
    prepareDeploy("0123456789abcdef0123456789abcdef01234567\n");

    expect(currentCommit())->toBe('0123456789abcdef0123456789abcdef01234567');
});

it('follows the branch when git packed its refs', function () {
    prepareDeploy(null);
    $git = deployTestDirectory().'/.git';
    mkdir($git);
    file_put_contents($git.'/HEAD', "ref: refs/heads/main\n");
    file_put_contents($git.'/packed-refs', "# pack-refs with: peeled fully-peeled sorted\n1111111111111111111111111111111111111111 refs/heads/other\n2222222222222222222222222222222222222222 refs/heads/main\n");

    expect(currentCommit())->toBe('2222222222222222222222222222222222222222');
});

it('reads a detached HEAD directly', function () {
    prepareDeploy(null);
    $git = deployTestDirectory().'/.git';
    mkdir($git);
    file_put_contents($git.'/HEAD', "3333333333333333333333333333333333333333\n");

    expect(currentCommit())->toBe('3333333333333333333333333333333333333333');
});

it('is null without a repository or with an unknown branch', function () {
    prepareDeploy(null);

    expect(currentCommit())->toBeNull();

    $git = deployTestDirectory().'/.git';
    mkdir($git);
    file_put_contents($git.'/HEAD', "ref: refs/heads/main\n");

    expect(currentCommit())->toBeNull();
});
