<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one place windows are drawn (docs/UI.md §6): at the end of `body`, never inside the top bar,
 * so a glass bar cannot trap a window. `open-window` names a window registered in `config('ui.windows')`
 * and `close-window` closes it; every window is a Livewire component that only calls Actions.
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
     * @param  array<string, mixed>  $params
     */
    #[On('open-window')]
    public function open(string $name, array $params = []): void
    {
        if (! is_string(config("ui.windows.{$name}"))) {
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
