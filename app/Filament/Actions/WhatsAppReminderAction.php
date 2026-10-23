<?php

namespace App\Filament\Actions;

use App\Actions\Reminders\BuildWhatsAppReminder;
use App\Models\Charge;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

/**
 * The WhatsApp icon on an open charge's row (G-5B.4, G-4C.6, docs/UI.md §2 Kural 5 and §4): a link
 * that opens a WhatsApp chat with the primary contact and a ready message about the charge in a
 * new tab. It is an icon button without visible text; its tooltip and the screen-reader label are
 * the translated "WhatsApp". Nothing is saved
 * and nothing asks for confirmation; the link comes from BuildWhatsAppReminder, and the button is
 * hidden when there is none (no phone number, archived customer, charge not open). Hidden below
 * `md` like the other secondary row actions, so a narrow row keeps only "Ödeme al".
 *
 * On a table row the record is the charge; on "Bugün", whose rows are not table rows, it comes in
 * as a `charge` argument. The page may hand over its loaded charges and one shared link builder
 * with {@see self::knownCharges()} and {@see self::linkBuilder()}, so rendering a row neither
 * queries nor reads the settings again.
 */
class WhatsAppReminderAction extends Action
{
    /** @var Collection<int, Charge>|null */
    protected ?Collection $knownCharges = null;

    protected ?BuildWhatsAppReminder $linkBuilder = null;

    /** @var array<int, ?string> */
    protected array $links = [];

    public static function getDefaultName(): ?string
    {
        return 'whatsAppReminder';
    }

    /**
     * @param  Collection<int, Charge>|null  $charges  Keyed by id.
     */
    public function knownCharges(?Collection $charges): static
    {
        $this->knownCharges = $charges;

        return $this;
    }

    public function linkBuilder(?BuildWhatsAppReminder $builder): static
    {
        $this->linkBuilder = $builder;

        return $this;
    }

    public function getRecord(bool $withDefault = true): ?Charge
    {
        $record = parent::getRecord($withDefault);

        if ($record instanceof Charge) {
            return $record;
        }

        $chargeId = $this->getArguments()['charge'] ?? null;

        return $chargeId === null ? null : ($this->knownCharges?->get($chargeId) ?? Charge::query()->find($chargeId));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('reminders.whatsapp.label'))
            ->icon(view('filament.icons.whatsapp'))
            ->iconButton()
            ->tooltip(__('reminders.whatsapp.label'))
            ->color('gray')
            ->extraAttributes(['class' => 'fi-hidden-below-md fi-whatsapp-action'])
            ->url(fn (?Charge $record): ?string => $this->linkFor($record), shouldOpenInNewTab: true)
            ->visible(fn (?Charge $record): bool => $this->linkFor($record) !== null);
    }

    private function linkFor(?Charge $record): ?string
    {
        if ($record === null) {
            return null;
        }

        return $this->links[$record->id] ??= ($this->linkBuilder ?? app(BuildWhatsAppReminder::class))->handle($record);
    }
}
