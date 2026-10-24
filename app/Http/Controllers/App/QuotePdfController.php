<?php

namespace App\Http\Controllers\App;

use App\Actions\Quotes\RenderQuotePdf;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the quote as the PDF that is e-mailed (RenderQuotePdf); allowed at every status.
 */
class QuotePdfController extends Controller
{
    public function __invoke(Quote $quote, RenderQuotePdf $renderPdf): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($quote, $renderPdf): void {
                echo $renderPdf->handle($quote);
            },
            $renderPdf->filename($quote),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
