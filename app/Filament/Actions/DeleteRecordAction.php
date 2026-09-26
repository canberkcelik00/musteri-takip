<?php

namespace App\Filament\Actions;

use App\Exceptions\LastActivePaymentMethod;
use App\Exceptions\RecordInUse;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Deletes a record through its delete Action (`handle(Model)`); shown only for records nothing
 * depends on (`isInUse()`), which otherwise can only be archived. A refusal from the Action
 * leaves the record as it is and is shown as a notification.
 */
class DeleteRecordAction extends DeleteAction
{
    /** @var class-string|null */
    protected ?string $deleteActionClass = null;

    /**
     * @param  class-string  $deleteActionClass
     */
    public function deleteWith(string $deleteActionClass): static
    {
        $this->deleteActionClass = $deleteActionClass;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalDescription(__('archive.delete_description'))
            ->successNotificationTitle(__('archive.deleted'))
            ->visible(fn (?Model $record): bool => $record !== null
                && method_exists($record, 'isInUse')
                && ! $record->isInUse())
            ->action(function (Model $record): void {
                assert($this->deleteActionClass !== null);

                try {
                    app($this->deleteActionClass)->handle($record);
                } catch (RecordInUse|LastActivePaymentMethod $exception) {
                    $this->failureNotificationTitle($exception->getMessage());
                    $this->failure();

                    return;
                }

                $this->success();
            });
    }
}
