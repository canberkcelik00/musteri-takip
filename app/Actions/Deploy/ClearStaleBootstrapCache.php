<?php

namespace App\Actions\Deploy;

use Illuminate\Filesystem\Filesystem;

class ClearStaleBootstrapCache
{
    /**
     * Name of the file in the cache directory that holds the commit the caches belong to.
     * It is not a `.php` file, so `optimize:clear` keeps it.
     */
    public const MARKER = 'release';

    public function __construct(
        private readonly string $gitDirectory,
        private readonly string $cacheDirectory,
        private readonly Filesystem $files = new Filesystem,
    ) {}

    /**
     * Remove the framework caches of the previous release once a new commit was pulled.
     *
     * Runs before the application boots (public/index.php, artisan): a cached package,
     * service, config or route list of the previous release could otherwise stop the new
     * code from booting, including the deploy endpoint that rebuilds the caches. It only
     * reads a few small files when nothing changed.
     */
    public function handle(): bool
    {
        $release = (new CurrentCommit($this->gitDirectory, $this->files))->id();

        if ($release === null || $release === $this->readId($this->markerFile())) {
            return false;
        }

        $this->files->delete($this->files->glob($this->cacheDirectory.'/*.php'));
        // Left behind by the releases that still shipped the Filament panel.
        $this->files->deleteDirectory($this->cacheDirectory.'/filament');
        $this->files->put($this->markerFile(), $release);

        return true;
    }

    private function markerFile(): string
    {
        return $this->cacheDirectory.'/'.self::MARKER;
    }

    private function readId(string $path): ?string
    {
        if (! $this->files->isFile($path)) {
            return null;
        }

        $id = trim($this->files->get($path));

        return $id === '' ? null : $id;
    }
}
