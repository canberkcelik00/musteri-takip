<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use Filament\Actions\ViewAction;

/**
 * Opens a payment's detail window (the amount in the payment list and in the card's "Son
 * ödemeler", at every width, G-4B.7): what the payment is, how it was allocated and, apart at the
 * bottom, "Sil" ({@see PaymentInfolist}). Clicking an amount never deletes anything by itself.
 */
class ViewPaymentAction extends ViewAction
{
    public static function getDefaultName(): ?string
    {
        return 'viewPayment';
    }

    /**
     * The window's footer button is the text "Kapat"; the panel-wide "Vazgeç" is for forms.
     */
    public static function make(?string $name = null): static
    {
        return parent::make($name)->modalCancelActionLabel(__('actions.close'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalHeading(__('payments.detail_heading'))
            ->disabledSchema(false)
            ->schema(fn (): array => PaymentInfolist::components());
    }
}
