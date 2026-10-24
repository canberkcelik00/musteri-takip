<?php

namespace App\Livewire\Windows;

use App\Actions\Servers\BuildServerUsageReport;
use App\Actions\Servers\RefreshServerTotals;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The read-only "Kullanım" window of a WHM server (docs/UI.md "Sunucu kullanım penceresi"): the reseller totals and
 * every account of the server. "Şimdi yenile" reads the server's totals and account list again and leaves the window
 * open with the new values. It only calls BuildServerUsageReport and RefreshServerTotals.
 */
class ViewServerUsage extends Component
{
    #[Locked]
    public int $serverId = 0;

    public function mount(int $server): void
    {
        $this->serverId = Server::query()->findOrFail($server)->id;
    }

    public function refreshUsage(): void
    {
        $usage = app(RefreshServerTotals::class)->handle(Server::query()->findOrFail($this->serverId));

        if ($usage->failure_reason === null) {
            $this->dispatch('notify', message: __('usage.refreshed'));
            $this->dispatch('servers-changed');

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
        $server = Server::query()->findOrFail($this->serverId);

        return view('livewire.windows.view-server-usage', [
            'server' => $server,
            'report' => app(BuildServerUsageReport::class)->handle($server),
        ]);
    }
}
