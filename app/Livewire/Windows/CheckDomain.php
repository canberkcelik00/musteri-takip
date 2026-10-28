<?php

namespace App\Livewire\Windows;

use App\Actions\Domains\CheckDomainDns;
use App\Actions\Domains\CheckDomainEmail;
use App\Actions\Domains\CheckDomainRegistration;
use App\Enums\DnsRecordType;
use App\Enums\EmailCheckStatus;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Support\Dns\HostName;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The read-only "Alan adını kontrol et" window (docs/UI.md "Alan adı kontrol penceresi"): the one window the
 * Ctrl+K row and the card's "Kontrol et" open. The DNS, WHOIS and E-posta tabs are live; the DNS lookups run after the
 * window is on screen (`wire:init`), the WHOIS and e-posta ones when their tab is first opened, and all again on
 * "Yeniden sorgula". It only calls CheckDomainDns, CheckDomainRegistration and CheckDomainEmail and saves nothing.
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

    public bool $registrationLoaded = false;

    /** @var array{expires_on: string|null, registrar: string|null, stored: bool, nameservers: list<string>, nameservers_differ: bool, mismatches: list<array{customer: string, service_end: string, days: int}>}|null */
    public ?array $registration = null;

    public bool $mailLoaded = false;

    /** @var array<string, array{status: EmailCheckStatus, record: string|null, reason: string|null}> */
    public array $mail = [];

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

    /**
     * The WHOIS tab reads on first use (and on "Yeniden sorgula" once it was read), never on opening the window.
     */
    public function loadRegistration(): void
    {
        if ($this->host === null || $this->registrationLoaded) {
            return;
        }

        $this->readRegistration();
    }

    /**
     * The E-posta tab reads on first use, like the WHOIS one.
     */
    public function loadMail(): void
    {
        if ($this->host === null || $this->mailLoaded) {
            return;
        }

        $this->readMail();
    }

    public function refresh(): void
    {
        $this->load();

        if ($this->registrationLoaded) {
            $this->readRegistration();
        }

        if ($this->mailLoaded) {
            $this->readMail();
        }
    }

    private function readMail(): void
    {
        $this->mail = app(CheckDomainEmail::class)->handle((string) $this->host) ?? [];
        $this->mailLoaded = true;
    }

    private function readRegistration(): void
    {
        $this->registration = app(CheckDomainRegistration::class)->handle((string) $this->host);
        $this->registrationLoaded = true;
    }

    public function render(): View
    {
        return view('livewire.windows.check-domain', ['types' => DnsRecordType::cases()]);
    }
}
