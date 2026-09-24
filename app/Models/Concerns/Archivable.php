<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Archiving through a nullable `archived_at` timestamp.
 *
 * Archived records are hidden from lists with local scopes only; there is no
 * global scope, so relations to an archived record (e.g. a charge's customer)
 * keep resolving.
 *
 * @property Carbon|null $archived_at
 */
trait Archivable
{
    /**
     * Initialize the trait for an instance.
     */
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    /**
     * Limit the query to records that are not archived.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('archived_at'));
    }

    /**
     * Limit the query to archived records.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function onlyArchived(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }

    public function unarchive(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }
}
