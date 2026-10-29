<?php

namespace App\Livewire\Pages\Auth;

use App\Actions\Account\ChangePassword;
use App\Actions\Account\ListMcpConnections;
use App\Actions\Account\SetMcpConnectionAccess;
use App\Actions\Account\UpdateProfile;
use App\Enums\McpAccess;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Whether each Claude connection (by connection id) may use the write tools; a connection starts read only (U-43).
     *
     * @var array<int, bool>
     */
    public array $write = [];

    public function mount(): void
    {
        $user = $this->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->loadConnections();
    }

    public function saveProfile(UpdateProfile $updateProfile): void
    {
        $user = $this->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $updateProfile->handle($user, $this->name, $this->email);

        $this->dispatch('notify', message: __('ui.profile.saved'));
    }

    public function changePassword(ChangePassword $changePassword): void
    {
        $this->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $changePassword->handle($this->user(), $this->current_password, $this->password);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('notify', message: __('ui.profile.password_changed'));
    }

    /**
     * The switch of a connection was flipped; only the user's own connection is found (U-43).
     */
    public function updatedWrite(mixed $value, string $key, SetMcpConnectionAccess $setAccess): void
    {
        $setAccess->handle($this->user(), (int) $key, $value ? McpAccess::ReadWrite : McpAccess::ReadOnly);

        $this->dispatch('notify', message: __('mcp.access_saved'));
    }

    /**
     * Re-read the list after the confirm window cut a connection.
     */
    #[On('mcp-connections-changed')]
    public function loadConnections(): void
    {
        $this->write = app(ListMcpConnections::class)->handle($this->user())
            ->mapWithKeys(fn (array $row): array => [$row['connection']->id => $row['connection']->access === McpAccess::ReadWrite])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.pages.auth.profile', [
            'connections' => app(ListMcpConnections::class)->handle($this->user()),
            'url' => route('mcp'),
            'command' => 'claude mcp add --scope user --transport http musteri-takip '.route('mcp'),
        ])->title(__('ui.profile.title'));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
