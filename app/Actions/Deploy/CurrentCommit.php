<?php

namespace App\Actions\Deploy;

use Illuminate\Filesystem\Filesystem;

class CurrentCommit
{
    public function __construct(
        private readonly string $gitDirectory,
        private readonly Filesystem $files = new Filesystem,
    ) {}

    /**
     * The commit the working tree is on, read from the repository's own files: the hosting
     * pulls with cPanel Git, and PHP there may not run the git binary. It also works before
     * the application boots. Null when there is no repository or HEAD cannot be resolved.
     */
    public function id(): ?string
    {
        $head = $this->read($this->gitDirectory.'/HEAD');

        if ($head === null || ! str_starts_with($head, 'ref:')) {
            return $head;
        }

        $ref = trim(substr($head, strlen('ref:')));

        return $this->read($this->gitDirectory.'/'.$ref) ?? $this->packedRef($ref);
    }

    /**
     * A ref that git moved into `packed-refs` (for example after `git gc`).
     */
    private function packedRef(string $ref): ?string
    {
        foreach (preg_split('/\R/', (string) $this->read($this->gitDirectory.'/packed-refs')) ?: [] as $line) {
            $parts = explode(' ', trim($line), 2);

            if (count($parts) === 2 && $parts[1] === $ref) {
                return $parts[0];
            }
        }

        return null;
    }

    private function read(string $path): ?string
    {
        if (! $this->files->isFile($path)) {
            return null;
        }

        $content = trim($this->files->get($path));

        return $content === '' ? null : $content;
    }
}
