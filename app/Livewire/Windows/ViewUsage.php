<?php

namespace App\Livewire\Windows;

use App\Actions\Servers\BuildUsageReport;
use App\Actions\Servers\RefreshAccountUsage;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\CustomerServiceItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The read-only "Kullanım" window of a linked hosting item (docs/UI.md "Kullanım penceresi"): the one window every
 * entry point opens (the card's badge, the edit window, "Bugün"). "Şimdi yenile" reads this one account from the
 * server and leaves the window open with the new values. It only calls BuildUsageReport and RefreshAccountUsage.
 */
class ViewUsage extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public int $itemId = 0;

    public function mount(int $item): void
    {
        $this->itemId = CustomerServiceItem::query()->findOrFail($item)->id;
    }

    public function refreshUsage(): void
    {
        $item = CustomerServiceItem::query()->with('server')->findOrFail($this->itemId);
        $report = app(BuildUsageReport::class)->handle($item);

        if ($report === null || $item->server === null) {
            return;
        }

        $usage = app(RefreshAccountUsage::class)->handle($item->server, $report->username);

        if ($usage->failure_reason === null) {
            $this->dispatch('notify', message: __('usage.refreshed'));

            return;
        }

        $this->dispatch(
            'notify',
            message: __('usage.refresh_failed', ['reason' => __('usage.failure.'.$usage->failure_reason->value)]).' '.__('servers.connection_'.$usage->failure_reason->value),
            tone: 'danger',
        );
    }

    public function render(): View
    {
        $item = CustomerServiceItem::query()->with('server')->findOrFail($this->itemId);

        return view('livewire.windows.view-usage', ['report' => app(BuildUsageReport::class)->handle($item)]);
    }
}
