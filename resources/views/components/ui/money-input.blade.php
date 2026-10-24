@props(['label' => null, 'suffix' => null])

{{-- An amount typed in Turkish format ("4.000,50"); the component keeps it a string, the Action parses it with Money. --}}
<x-ui.input :label="$label" :suffix="$suffix" inputmode="decimal" placeholder="0,00" autocomplete="off" {{ $attributes }} />
