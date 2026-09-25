<?php

namespace App\Filament\Resources\CatalogServices\Pages;

use App\Filament\Actions\ArchiveAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use Filament\Resources\Pages\EditRecord;

class EditCatalogService extends EditRecord
{
    protected static string $resource = CatalogServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ArchiveAction::make(),
            UnarchiveAction::make(),
        ];
    }
}
