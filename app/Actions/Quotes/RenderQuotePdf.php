<?php

namespace App\Actions\Quotes;

use App\Models\Quote;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class RenderQuotePdf
{
    /**
     * Logo formats the PDF library can embed without extra extensions.
     */
    private const LOGO_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif'];

    public function __construct(private Settings $settings) {}

    /**
     * The quote as PDF bytes: company details and logo, number, dates, recipient, items (the
     * contents of a package under its line, free items as "Ücretsiz"), totals per currency and notes.
     */
    public function handle(Quote $quote): string
    {
        return Pdf::loadHTML($this->html($quote))->setPaper('a4')->output();
    }

    /**
     * The file name the PDF is attached and downloaded as.
     */
    public function filename(Quote $quote): string
    {
        return $quote->number.'.pdf';
    }

    /**
     * The HTML the PDF is made from; also what the tests read, because PDF text is compressed.
     */
    public function html(Quote $quote): string
    {
        $quote->load(['customer.primaryContact', 'items.package.catalogServices']);

        return view('pdf.quote', [
            'quote' => $quote,
            'company' => $this->settings->companyDetails(),
            'logo' => $this->logo(),
            'recipient' => $this->recipient($quote),
            'items' => $quote->items,
            'totals' => $quote->totals(),
            'format' => fn (int $amount, $currency): string => (new Money($amount, $currency))->format(),
        ])->render();
    }

    /**
     * The logo as a data URI, so no file link or symlink is needed; none when it is not set,
     * missing or not a supported image.
     */
    private function logo(): ?string
    {
        $path = $this->settings->companyDetails()['logo_path'];
        $disk = Storage::disk('local');

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $mimeType = $disk->mimeType($path);

        if (! in_array($mimeType, self::LOGO_MIME_TYPES, true)) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * The recipient's lines in print order.
     *
     * @return list<string>
     */
    private function recipient(Quote $quote): array
    {
        $customer = $quote->customer;

        if ($customer === null) {
            return $this->present([
                $quote->recipient_company,
                $quote->recipient_name,
                $quote->recipient_phone,
                $quote->recipient_email,
            ]);
        }

        $contact = $customer->primaryContact;

        return $this->present([
            $customer->legal_title ?? $customer->short_name,
            $contact?->name,
            $customer->billing_address,
            $contact?->phone,
            $contact?->email,
        ]);
    }

    /**
     * @param  list<?string>  $lines
     * @return list<string>
     */
    private function present(array $lines): array
    {
        return array_values(array_filter($lines, fn (?string $line): bool => $line !== null && $line !== ''));
    }
}
