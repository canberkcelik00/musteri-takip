@php
    use App\Support\Ui\Navigation;
@endphp
<aside class="ui-sidebar ui-glass" x-bind:class="{ 'is-open': menu }" aria-label="{{ __('ui.nav.main') }}">
    <x-ui.brand />

    @foreach (Navigation::entries() as $entry)
        @if (isset($entry['group']))
            <div x-data="{ open: @js($entry['open']) }">
                @if ($entry['collapsed'])
                    <button type="button" class="ui-nav-item ui-nav-group-toggle" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span class="ui-nav-group" style="margin: 0">{{ __($entry['group']) }}</span>
                        <x-heroicon-m-chevron-right class="ui-nav-chevron" x-bind:class="{ 'is-open': open }" aria-hidden="true" />
                    </button>
                @else
                    <div class="ui-nav-group">{{ __($entry['group']) }}</div>
                @endif

                <div x-show="open" @if (! $entry['open']) x-cloak @endif>
                    @foreach ($entry['items'] as $item)
                        <x-ui.nav-item :item="$item" />
                    @endforeach
                </div>
            </div>
        @else
            <x-ui.nav-item :item="$entry" />
        @endif
    @endforeach
</aside>
