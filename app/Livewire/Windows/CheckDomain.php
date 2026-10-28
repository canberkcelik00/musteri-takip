<?php

namespace App\Livewire\Windows;

use App\Actions\Domains\CheckDomainDns;
use App\Enums\DnsRecordType;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Support\Dns\HostName;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The read-only "Alan adını kontrol et" window (docs/UI.md "Alan adı kontrol penceresi"): the one window the
 * Ctrl+K row and the card's "Kontrol et" open. The DNS tab is live; the lookups run after the window is on screen
 * (`wire:init`) and again on "Yeniden sorgula", never on a tab change. It only calls CheckDomainDns and saves nothing.
 */
class CheckDomain extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public ?string $host = null;

    public string $typed = '';

    public bool $loaded = false;

    /** @var array<string, list<string>|null> */
    public array $records = [];

    public function mount(string $domain): void
    {
        $this->typed = $domain;
        $this->host = HostName::normalize($domain);
    }

    public function load(): void
    {
        if ($this->host === null) {
            return;
        }

        $this->records = app(CheckDomainDns::class)->handle($this->host) ?? [];
        $this->loaded = true;
    }

    public function render(): View
    {
        return view('livewire.windows.check-domain', ['types' => DnsRecordType::cases()]);
    }
}
