<?php

namespace App\Actions\CatalogServices;

use App\Exceptions\RecordInUse;
use App\Models\CatalogService;
use Illuminate\Support\Facades\DB;

class DeleteCatalogService
{
    /**
     * Delete a catalog service that is in no package and was sold to no customer; one in use is
     * archived instead. The row is locked so a concurrent insert that references it cannot slip
     * in between the check and the delete.
     *
     * @throws RecordInUse
     */
    public function handle(CatalogService $catalogService): void
    {
        DB::transaction(function () use ($catalogService): void {
            $locked = CatalogService::query()->lockForUpdate()->find($catalogService->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->isInUse()) {
                throw new RecordInUse('catalog_service');
            }

            $locked->delete();
        });
    }
}
