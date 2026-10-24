<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Placeholder of the glass interface's home until the Today screen moves here (G-10.5).
 */
#[Layout('layouts.app')]
class Today extends Component
{
    public function render(): View
    {
        return view('livewire.pages.today')->title(__('ui.nav.today'));
    }
}
