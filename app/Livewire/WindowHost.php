<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one place windows are drawn (docs/UI.md §6): at the end of `body`, never inside the top bar,
 * so a glass bar cannot trap a window. `open-window` names a window registered in `config('ui.windows')`
 * and `close-window` closes it (a window whose permission the user lacks is not opened, see
 * `config('ui.window_permissions')`); every window is a Livewire component that only calls Actions.
 */
class WindowHost extends Component
{
    #[Locked]
    public ?string $name = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $params = [];

    /** Counts openings, so each opening is a fresh component (no state left from the last one). */
    #[Locked]
    public int $opened = 0;

    /**
     * A full redirect drops the events its component dispatched, so a window that sends the user to another page
     * leaves its notification and the window to open there under this one-use session key:
     * `['message' => string, 'window' => ?string, 'params' => array]`.
     */
    public const string AFTER_REDIRECT = 'window_host.after_redirect';

    public function mount(): void
    {
        $pending = session()->pull(self::AFTER_REDIRECT);

        if (! is_array($pending)) {
            return;
        }

        if (is_string($pending['message'] ?? null)) {
            $this->dispatch('notify', message: $pending['message']);
        }

        if (is_string($pending['window'] ?? null)) {
            $this->open($pending['window'], is_array($pending['params'] ?? null) ? $pending['params'] : []);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    #[On('open-window')]
    public function open(string $name, array $params = []): void
    {
        if (! is_string(config("ui.windows.{$name}"))) {
            return;
        }

        $permission = config("ui.window_permissions.{$name}");

        if (is_string($permission) && ! Auth::user()?->can($permission)) {
            return;
        }

        $this->name = $name;
        $this->params = $params;
        $this->opened++;
    }

    #[On('close-window')]
    public function close(): void
    {
        $this->name = null;
        $this->params = [];
    }

    public function render(): View
    {
        $class = $this->name === null ? null : config("ui.windows.{$this->name}");

        return view('livewire.window-host', ['class' => is_string($class) ? $class : null]);
    }
}
