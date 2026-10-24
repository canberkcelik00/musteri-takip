<?php

namespace App\Livewire\Pages\Settings;

use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The payment methods (docs/UI.md §5 Ayarlar), in the order the payment form offers them. A row is dragged to a new
 * place (or moved with its arrows, for the keyboard); the new order is written to `sort_order`. Archived methods are
 * hidden unless asked for.
 */
#[Layout('layouts.app')]
class PaymentMethods extends Component
{
    public const string ARCHIVE_ACTIVE = 'active';

    public const string ARCHIVE_ARCHIVED = 'archived';

    public const string ARCHIVE_ALL = 'all';

    #[Url(except: self::ARCHIVE_ACTIVE)]
    public string $archive = self::ARCHIVE_ACTIVE;

    /**
     * A method was saved in a window: the list is read again.
     */
    #[On('payment-methods-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * Writes the dragged order. The shown rows keep the `sort_order` slots they occupied, so rows that are hidden by
     * the archive filter keep their places.
     *
     * @param  array<int, mixed>  $ids
     */
    public function reorder(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $slots = PaymentMethod::query()->whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->pluck('sort_order');

        if ($slots->count() !== count($ids)) {
            return;
        }

        DB::transaction(function () use ($ids, $slots): void {
            foreach ($ids as $position => $id) {
                PaymentMethod::query()->whereKey($id)->update(['sort_order' => $slots[$position]]);
            }
        });
    }

    public function move(int $id, int $step): void
    {
        $ids = $this->methods()->pluck('id')->all();
        $index = array_search($id, $ids, true);
        $target = $index === false ? null : $index + $step;

        if ($index === false || ! isset($ids[$target])) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->reorder($ids);
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    private function methods(): Collection
    {
        $query = PaymentMethod::query()->orderBy('sort_order')->orderBy('id');

        match ($this->archive) {
            self::ARCHIVE_ARCHIVED => $query->onlyArchived(),
            self::ARCHIVE_ALL => null,
            default => $query->notArchived(),
        };

        return $query->get();
    }

    public function render(): View
    {
        return view('livewire.pages.settings.payment-methods', ['methods' => $this->methods()])->title(__('payment_methods.plural'));
    }
}
