<?php

namespace App\Filament\Tables\Filters;

use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Archive filter for lists of Archivable records; archived records are hidden by default.
 */
class ArchivedFilter extends TernaryFilter
{
    public static function getDefaultName(): ?string
    {
        return 'archived';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('archive.filter'))
            ->placeholder(__('archive.filter_all'))
            ->trueLabel(__('archive.filter_archived'))
            ->falseLabel(__('archive.filter_not_archived'))
            ->default(false)
            ->queries(
                true: fn (Builder $query): Builder => $query->scopes(['onlyArchived']),
                false: fn (Builder $query): Builder => $query->scopes(['notArchived']),
                blank: fn (Builder $query): Builder => $query,
            );
    }
}
