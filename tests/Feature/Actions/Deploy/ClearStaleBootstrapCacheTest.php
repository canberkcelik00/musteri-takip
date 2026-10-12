<?php

use App\Actions\Deploy\ClearStaleBootstrapCache;

afterEach(fn () => cleanUpDeploy());

/**
 * A cache directory with the files a booted release leaves behind, inside the temporary
 * directory `prepareDeploy()` created.
 */
function staleCacheDirectory(): string
{
    $cache = deployTestDirectory().'/cache';
    mkdir($cache.'/filament', recursive: true);

    foreach (['packages.php', 'services.php', 'config.php', 'routes-v7.php'] as $file) {
        file_put_contents($cache.'/'.$file, '<?php return [];');
    }

    file_put_contents($cache.'/filament/components.php', '<?php return [];');
    file_put_contents($cache.'/.gitignore', "*\n!.gitignore\n");

    return $cache;
}

function clearStaleBootstrapCache(string $cache): bool
{
    return (new ClearStaleBootstrapCache(deployTestDirectory().'/RELEASE', $cache))->handle();
}

/**
 * @return list<string>
 */
function cacheEntries(string $cache): array
{
    $entries = array_values(array_diff(scandir($cache), ['.', '..']));
    sort($entries);

    return $entries;
}

it('removes the caches of the previous release once a new release was pulled', function () {
    prepareDeploy('release-2');
    $cache = staleCacheDirectory();
    file_put_contents($cache.'/release', 'release-1');

    expect(clearStaleBootstrapCache($cache))->toBeTrue()
        ->and(cacheEntries($cache))->toBe(['.gitignore', 'release'])
        ->and(file_get_contents($cache.'/release'))->toBe('release-2');
});

it('removes the caches on the first run, when there is no marker yet', function () {
    prepareDeploy('release-1');
    $cache = staleCacheDirectory();

    expect(clearStaleBootstrapCache($cache))->toBeTrue()
        ->and(cacheEntries($cache))->toBe(['.gitignore', 'release'])
        ->and(file_get_contents($cache.'/release'))->toBe('release-1');
});

it('touches nothing while the release is the same', function () {
    prepareDeploy("release-1\n");
    $cache = staleCacheDirectory();
    file_put_contents($cache.'/release', 'release-1');
    $before = cacheEntries($cache);

    expect(clearStaleBootstrapCache($cache))->toBeFalse()
        ->and(cacheEntries($cache))->toBe($before)
        ->and(is_file($cache.'/filament/components.php'))->toBeTrue();
});

it('touches nothing when there is no RELEASE file', function (?string $release) {
    prepareDeploy($release);
    $cache = staleCacheDirectory();
    $before = cacheEntries($cache);

    expect(clearStaleBootstrapCache($cache))->toBeFalse()
        ->and(cacheEntries($cache))->toBe($before);
})->with([
    'missing' => [null],
    'empty' => ["  \n"],
]);
