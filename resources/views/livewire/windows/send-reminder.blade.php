<x-ui.window :title="__('reminders.send_heading')" submit="send">
    <p class="ui-m-0">{{ $text }}</p>

    <x-slot:footer>
        <x-ui.button x-on:click="$dispatch('close-window')">{{ $canSend ? __('actions.discard') : __('actions.close') }}</x-ui.button>
        @if ($canSend)
            <x-ui.button type="submit" variant="primary">{{ __('reminders.send') }}</x-ui.button>
        @endif
    </x-slot:footer>
</x-ui.window>
