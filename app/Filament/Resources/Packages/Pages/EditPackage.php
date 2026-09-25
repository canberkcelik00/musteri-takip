<?php

namespace App\Filament\Resources\Packages\Pages;

use App\Filament\Actions\ArchiveAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\Packages\PackageResource;
use Filament\Resources\Pages\EditRecord;

class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ArchiveAction::make(),
            UnarchiveAction::make(),
        ];
    }
}
