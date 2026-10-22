<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Actions\CreateQuoteAction;
use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateQuoteAction::make()->color('gray'),
        ];
    }

    /**
     * A quote changed through another component (a window's action); the list just renders again.
     */
    #[On(CreateQuoteAction::QUOTES_CHANGED_EVENT)]
    public function refreshAfterQuoteChange(): void {}
}
