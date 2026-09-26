<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Tells whether other records depend on this one, i.e. whether it can be deleted or may only
 * be archived. `withInUse()` loads the answer with a list query so rows do not query one by one.
 */
trait TracksUsage
{
    /**
     * Relations whose records make this record "in use".
     *
     * @return list<string>
     */
    abstract protected function usageRelations(): array;

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withInUse(Builder $query): void
    {
        $query->withExists($this->usageRelations());
    }

    public function isInUse(): bool
    {
        foreach ($this->usageRelations() as $relation) {
            $loaded = Str::snake($relation).'_exists';

            $inUse = array_key_exists($loaded, $this->attributes)
                ? (bool) $this->attributes[$loaded]
                : $this->{$relation}()->exists();

            if ($inUse) {
                return true;
            }
        }

        return false;
    }
}
