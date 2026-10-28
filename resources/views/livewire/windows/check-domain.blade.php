<x-ui.window :title="$host ?? __('domain_check.heading')">
    @if ($host === null)
        <div class="ui-error-box" role="alert">{{ __('domain_check.invalid', ['domain' => $typed]) }}</div>
    @else
        <div x-data="{ tab: 'dns' }" @if (! $loaded) wire:init="load" @endif>
            <x-ui.segmented
                x-model="tab"
                :options="[
                    'dns' => __('domain_check.tab_dns'),
                    'ssl' => __('domain_check.tab_ssl'),
                    'whois' => __('domain_check.tab_whois'),
                    'mail' => __('domain_check.tab_mail'),
                ]"
            />

            <div x-show="tab === 'dns'" class="ui-mt-3">
                @if (! $loaded)
                    <p class="ui-hint" role="status">{{ __('domain_check.loading') }}</p>
                @else
                    @foreach ($types as $type)
                        @php($rows = $records[$type->value] ?? null)
                        <h4 class="ui-form-section">{{ __('domain_check.type.'.$type->value) }}</h4>
                        @if ($rows === null)
                            <p class="ui-hint">{{ __('domain_check.unreadable') }}</p>
                        @elseif ($rows === [])
                            <p class="ui-hint">{{ __('domain_check.no_record') }}</p>
                        @else
                            <ul class="ui-mailboxes">
                                @foreach ($rows as $row)
                                    <li><span class="ui-mailbox-address">{{ $row }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                @endif
            </div>

            @foreach (['ssl', 'whois', 'mail'] as $tab)
                <p x-show="tab === '{{ $tab }}'" x-cloak class="ui-note ui-mt-3">{{ __('domain_check.coming_soon') }}</p>
            @endforeach
        </div>
    @endif

    <x-slot:footer>
        @if ($host !== null)
            <x-ui.button wire:click="load" class="ui-mr-auto">{{ __('domain_check.refresh') }}</x-ui.button>
        @endif
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
