<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Archives an Archivable record after confirmation; hidden for records that are already archived.
 *
 * Only for records without an archive rule of their own; a record with one (e.g. a customer)
 * calls its Action instead.
 */
class ArchiveAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'archive';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('archive.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('archive.archive_heading'))
            ->modalDescription(__('archive.archive_description'))
            ->successNotificationTitle(__('archive.archived'))
            ->visible(fn (?Model $record): bool => $record !== null && method_exists($record, 'isArchived') && ! $record->isArchived())
            ->action(function (Model $record): void {
                assert(method_exists($record, 'archive'));

                $record->archive();

                $this->success();
            });
    }
}
