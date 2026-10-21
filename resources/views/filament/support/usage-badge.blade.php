<span class="fi-usage-badge" role="button" tabindex="0" wire:click.stop="mountAction('viewUsage', { item: {{ $itemId }} })" wire:keydown.enter.stop="mountAction('viewUsage', { item: {{ $itemId }} })">
    <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
</span>
