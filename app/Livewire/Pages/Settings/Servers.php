<?php

namespace App\Livewire\Pages\Settings;

use App\Actions\Servers\BuildResellerSummary;
use App\Actions\Servers\ListUsageAlerts;
use App\Enums\UsageLevel;
use App\Models\AccountUsage;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The servers (docs/UI.md §5 Ayarlar), by name. The name opens the edit window, the "Kullanım" cell the usage window
 * of a WHM server, and the row's one button "Sunucudan çek". Usage summaries are built once per render from the
 * eager-loaded readings. Archived servers are hidden unless asked for.
 */
#[Layout('layouts.app')]
class Servers extends Component
{
    public const string ARCHIVE_ACTIVE = 'active';

    public const string ARCHIVE_ARCHIVED = 'archived';

    public const string ARCHIVE_ALL = 'all';

    #[Url(except: self::ARCHIVE_ACTIVE)]
    public string $archive = self::ARCHIVE_ACTIVE;

    /**
     * A server was saved in a window: the list is read again.
     */
    #[On('servers-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * @return Collection<int, Server>
     */
    private function servers(): Collection
    {
        $query = Server::query()->with(['accountUsages', 'serverUsage'])->orderBy('name')->orderBy('id');

        match ($this->archive) {
            self::ARCHIVE_ARCHIVED => $query->onlyArchived(),
            self::ARCHIVE_ALL => null,
            default => $query->notArchived(),
        };

        return $query->get();
    }

    /**
     * The "Kullanım" cell of each server: the last reading (or why it failed), how many warnings there are, and the
     * reseller line of a WHM server.
     *
     * @param  Collection<int, Server>  $servers
     * @return array<int, array{text: string, failed: bool, reseller: ?string, resellerLevel: ?UsageLevel}>
     */
    private function usageOf(Collection $servers): array
    {
        $warnings = $this->warningsByServer();
        $usage = [];

        foreach ($servers as $server) {
            $failed = $server->accountUsages->first(fn (AccountUsage $row): bool => $row->failure_reason !== null)
                ?? ($server->serverUsage?->failure_reason !== null ? $server->serverUsage : null);
            $refreshedAt = collect([$server->accountUsages->max('refreshed_at'), $server->serverUsage?->refreshed_at])->filter()->max();
            $count = $warnings[$server->id] ?? 0;

            $text = match (true) {
                $failed !== null => __('usage.servers_column_failed', ['reason' => __('usage.failure.'.$failed->failure_reason->value)]),
                $refreshedAt === null => __('usage.never_read'),
                default => $refreshedAt->format('d.m.Y H:i').($count > 0 ? ' · '.__('usage.servers_column_warnings', ['count' => $count]) : ''),
            };

            $summary = $server->serverUsage === null ? null : app(BuildResellerSummary::class)->handle($server->serverUsage);

            $usage[$server->id] = [
                'text' => $text,
                'failed' => $failed !== null,
                'reseller' => $summary['text'] ?? null,
                'resellerLevel' => $summary['level'] ?? null,
            ];
        }

        return $usage;
    }

    /**
     * How many usage warnings each server has right now, by server id.
     *
     * @return array<int, int>
     */
    private function warningsByServer(): array
    {
        $serverOf = AccountUsage::query()->pluck('server_id', 'id');
        $counts = [];

        foreach (app(ListUsageAlerts::class)->handle() as $alert) {
            $serverId = $alert->serverId ?? $serverOf[$alert->accountUsageId] ?? null;

            if ($serverId !== null) {
                $counts[$serverId] = ($counts[$serverId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function render(): View
    {
        $servers = $this->servers();

        return view('livewire.pages.settings.servers', [
            'servers' => $servers,
            'usage' => $this->usageOf($servers),
        ])->title(__('servers.plural'));
    }
}
