<x-ui.window :title="$text['heading']" submit="confirm">
    @if ($text['description'] !== '')
        <p style="margin: 0">{{ $text['description'] }}</p>
    @endif

    <x-slot:footer>
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.discard') }}</x-ui.button>
        <x-ui.button type="submit" :variant="$text['danger'] ? 'danger' : 'primary'">{{ $text['label'] }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
