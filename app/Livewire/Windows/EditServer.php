<?php

namespace App\Livewire\Windows;

use App\Actions\Servers\TestServerConnection;
use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or edits a server connection (docs/UI.md §5 Ayarlar). The API token is never shown again: a blank token keeps
 * the saved one. A cPanel server also names the hosting service its domains are assigned with. "Bağlantıyı dene"
 * tests the saved connection; the rarer actions (archive, delete) sit in a named section.
 */
class EditServer extends Component
{
    #[Locked]
    public ?int $serverId = null;

    public string $type = '';

    public string $name = '';

    public string $hostname = '';

    public string $port = '';

    public string $username = '';

    public string $api_token = '';

    public string $catalog_service_id = '';

    public function mount(?int $server = null): void
    {
        if ($server === null) {
            $this->type = ServerType::Whm->value;
            $this->port = (string) ServerType::Whm->defaultPort();

            return;
        }

        $record = Server::query()->findOrFail($server);

        $this->serverId = $record->id;
        $this->type = $record->type->value;
        $this->name = $record->name;
        $this->hostname = $record->hostname;
        $this->port = (string) $record->port;
        $this->username = $record->username;
        $this->catalog_service_id = $record->catalog_service_id === null ? '' : (string) $record->catalog_service_id;
    }

    /**
     * Picking a type fills in that type's usual port.
     */
    public function updatedType(): void
    {
        $type = ServerType::tryFrom($this->type);

        if ($type !== null) {
            $this->port = (string) $type->defaultPort();
        }
    }

    public function save(): void
    {
        $this->validate();

        $isCpanel = $this->type === ServerType::Cpanel->value;

        $attributes = [
            'type' => ServerType::from($this->type),
            'name' => $this->name,
            'hostname' => $this->hostname,
            'port' => (int) $this->port,
            'username' => $this->username,
            'catalog_service_id' => $isCpanel ? (int) $this->catalog_service_id : null,
        ];

        if (trim($this->api_token) !== '') {
            $attributes['api_token'] = trim($this->api_token);
        }

        if ($this->serverId === null) {
            Server::query()->create($attributes);
        } else {
            Server::query()->findOrFail($this->serverId)->update($attributes);
        }

        $this->dispatch('servers-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('servers.saved'));
    }

    public function testConnection(TestServerConnection $testServerConnection): void
    {
        if ($this->serverId === null) {
            return;
        }

        $result = $testServerConnection->handle(Server::query()->findOrFail($this->serverId));

        $this->dispatch('notify', message: $result->message, tone: $result->successful ? 'success' : 'danger');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $isCpanel = $this->type === ServerType::Cpanel->value;

        return [
            'type' => ['required', Rule::enum(ServerType::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique('servers', 'name')->ignore($this->serverId)],
            'hostname' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'api_token' => [$this->serverId === null ? 'required' : 'nullable', 'string', 'max:2000'],
            'catalog_service_id' => [
                $isCpanel ? 'required' : 'nullable',
                Rule::exists('catalog_services', 'id')->where('type', ServiceType::Hosting->value)->whereNull('archived_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'type' => __('servers.type'),
            'name' => __('servers.name'),
            'hostname' => __('servers.hostname'),
            'port' => __('servers.port'),
            'username' => __('servers.username'),
            'api_token' => __('servers.api_token'),
            'catalog_service_id' => __('servers.catalog_service'),
        ];
    }

    public function render(): View
    {
        $record = $this->serverId === null ? null : Server::query()->find($this->serverId);

        return view('livewire.windows.edit-server', [
            'record' => $record,
            'inUse' => $record?->isInUse() ?? false,
            'types' => collect(ServerType::cases())->mapWithKeys(fn (ServerType $case): array => [$case->value => $case->getLabel()])->all(),
            'hostingServices' => CatalogService::query()->notArchived()->where('type', ServiceType::Hosting)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
