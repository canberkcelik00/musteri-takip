<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\RenderQuotePdf;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the quote as the PDF that is e-mailed (RenderQuotePdf); allowed at every status.
 */
class DownloadQuotePdfAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'downloadQuotePdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('quotes.download'))
            ->icon(Heroicon::ArrowDownTray)
            ->action(fn (Quote $record, RenderQuotePdf $renderPdf): StreamedResponse => response()->streamDownload(
                function () use ($record, $renderPdf): void {
                    echo $renderPdf->handle($record);
                },
                $renderPdf->filename($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }
}
