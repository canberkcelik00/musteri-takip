<?php

namespace App\Actions\Deploy;

use App\Actions\EnsureAdminUser;
use App\Enums\DeployResult;
use BladeUI\Icons\IconsManifest;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeployRelease
{
    public function __construct(
        private readonly ArtisanRunner $artisan,
        private readonly EnsureAdminUser $ensureAdminUser,
        private readonly IconsManifest $iconsManifest,
    ) {}

    /**
     * Prepare the uploaded release (migrations, caches) when it has not been deployed yet.
     *
     * The release id is the content of the RELEASE file the deploy package ships; the
     * last deployed id is kept in the storage directory, which a deploy never overwrites.
     * The id is stored only after every step succeeded, so a failed deploy is tried
     * again on the next call. A file lock keeps the endpoint and the cron from
     * running it at the same time.
     *
     * @throws RuntimeException when a step fails (logged with its details)
     */
    public function handle(): DeployResult
    {
        $release = $this->currentRelease();

        if ($release === null) {
            return DeployResult::NoRelease;
        }

        if ($release === $this->deployedRelease()) {
            return DeployResult::UpToDate;
        }

        $lock = $this->lockStore()->lock('deploy', 600);

        if (! $lock->get()) {
            return DeployResult::Busy;
        }

        try {
            if ($this->currentRelease() === $this->deployedRelease()) {
                return DeployResult::UpToDate;
            }

            $this->prepare();
            File::put($this->stateFile(), $release);

            return DeployResult::Deployed;
        } catch (Throwable $exception) {
            Log::error('Deploy failed.', ['release' => $release, 'exception' => $exception]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether a release was uploaded that has not been deployed yet.
     */
    public function hasPendingRelease(): bool
    {
        $release = $this->currentRelease();

        return $release !== null && $release !== $this->deployedRelease();
    }

    /**
     * The file cache store: unlike the database store it needs no table, so the lock
     * also works on the first deploy.
     */
    private function lockStore(): LockProvider
    {
        /** @var CacheRepository $cache */
        $cache = Cache::store('file');
        $store = $cache->getStore();

        if (! $store instanceof LockProvider) {
            throw new RuntimeException('The file cache store does not support locks.');
        }

        return $store;
    }

    /**
     * Filament's component cache is handled explicitly: `optimize` only knows it when it
     * runs in the console, not when the deploy endpoint calls it. The combined
     * `filament:optimize(-clear)` is not used because it also calls the Blade Icons
     * commands, which are not registered in a web request, so the icon manifest is not
     * cached here. The console `optimize` does write it, though, and `icons:clear` cannot
     * run in a web request: the package's own class removes the stale manifest instead
     * (it knows the file path, so no path is repeated here).
     *
     * The application cache is not flushed: it lives in the database, whose `cache` table
     * does not exist before the first migration, and no release depends on it being empty.
     */
    private function prepare(): void
    {
        $this->run('optimize:clear', ['--except' => 'cache']);
        $this->iconsManifest->delete();
        $this->run('filament:clear-cached-components');
        $this->run('migrate', ['--force' => true]);

        $this->ensureAdminUser->handle();

        $this->run('optimize');
        $this->run('filament:cache-components');

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function run(string $command, array $parameters = []): void
    {
        $exitCode = $this->artisan->run($command, $parameters);

        if ($exitCode !== 0) {
            throw new RuntimeException("Deploy step [{$command}] failed with exit code {$exitCode}.");
        }
    }

    private function currentRelease(): ?string
    {
        return $this->readId((string) config('app.deploy.release_file'));
    }

    private function deployedRelease(): ?string
    {
        return $this->readId($this->stateFile());
    }

    private function stateFile(): string
    {
        return (string) config('app.deploy.state_file');
    }

    private function readId(string $path): ?string
    {
        if (! File::isFile($path)) {
            return null;
        }

        $id = trim(File::get($path));

        return $id === '' ? null : $id;
    }
}
