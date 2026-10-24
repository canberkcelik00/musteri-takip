<?php

namespace Tests\Fixtures\Ui;

use Livewire\Component;

/**
 * A stand-in window for the WindowHost tests; real windows arrive with the screens that use them.
 */
class ExampleWindow extends Component
{
    public string $subject = '';

    public function render(): string
    {
        return <<<'blade'
        <x-ui.window title="Deneme penceresi">
            <p>{{ $subject }}</p>
            <x-slot:footer>
                <x-ui.button x-on:click="$dispatch('close-window')">Vazgeç</x-ui.button>
            </x-slot:footer>
        </x-ui.window>
        blade;
    }
}
