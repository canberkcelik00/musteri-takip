@props(['href' => null])

{{--
    A table row. With `href` the whole row opens it (a click that lands on a link, button or field keeps its own
    job) and ends in a gray "›"; the `actions` slot puts row buttons before it.
--}}
<tr
    {{ $attributes->class(['ui-row']) }}
    @if ($href)
        data-href="{{ $href }}"
        x-data
        x-on:click="if (! $event.target.closest('a, button, input, select, textarea, label')) { window.Livewire ? Livewire.navigate($el.dataset.href) : window.location.href = $el.dataset.href }"
    @endif
>
    {{ $slot }}
    @if ($href || isset($actions))
        <td class="ui-row-end is-end">
            <div class="ui-row-end-inner">
                {{ $actions ?? '' }}
                @if ($href)
                    <x-heroicon-m-chevron-right class="ui-chevron" aria-hidden="true" />
                @endif
            </div>
        </td>
    @endif
</tr>
