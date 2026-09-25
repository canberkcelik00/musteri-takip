<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Restores an archived Archivable record; hidden for records that are not archived.
 */
class UnarchiveAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'unarchive';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('archive.unarchive'))
            ->icon(Heroicon::OutlinedArchiveBoxXMark)
            ->color('gray')
            ->successNotificationTitle(__('archive.unarchived'))
            ->visible(fn (?Model $record): bool => $record !== null && method_exists($record, 'isArchived') && $record->isArchived())
            ->action(function (Model $record): void {
                assert(method_exists($record, 'unarchive'));

                $record->unarchive();

                $this->success();
            });
    }
}
