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

            <div x-show="tab === 'whois'" x-cloak x-effect="if (tab === 'whois') $wire.loadRegistration()" class="ui-mt-3">
                @if (! $registrationLoaded)
                    <p class="ui-hint" role="status">{{ __('domain_check.loading') }}</p>
                @elseif ($registration === null || ($registration['expires_on'] === null && $registration['registrar'] === null))
                    <p class="ui-hint">{{ __('domain_check.unreadable') }}</p>
                @else
                    <ul class="ui-mailboxes">
                        <li><span class="ui-mailbox-address">{{ __('domain_check.registration_ends') }}: {{ $registration['expires_on'] === null ? '—' : \Illuminate\Support\Carbon::parse($registration['expires_on'])->format('d.m.Y') }}</span></li>
                        <li><span class="ui-mailbox-address">{{ __('domain_check.registrar') }}: {{ $registration['registrar'] ?? '—' }}</span></li>
                        @foreach ($registration['nameservers'] as $nameserver)
                            <li><span class="ui-mailbox-address">{{ __('domain_check.nameserver') }}: {{ $nameserver }}</span></li>
                        @endforeach
                    </ul>
                    @if ($registration['nameservers_differ'])
                        <p class="ui-note ui-mt-3" role="alert">{{ __('domain_check.nameservers_differ') }}</p>
                    @endif
                    @if ($registration['stored'])
                        <p class="ui-hint">{{ __('domain_check.stored_answer') }}</p>
                    @endif
                    @foreach ($registration['mismatches'] as $mismatch)
                        @php($serviceEnd = \Illuminate\Support\Carbon::parse($mismatch['service_end'])->format('d.m.Y'))
                        <p class="ui-note ui-mt-3" role="alert">{{ __('domain_check.mismatch', ['customer' => $mismatch['customer'], 'service' => $serviceEnd, 'days' => $mismatch['days']]) }}</p>
                    @endforeach
                @endif
            </div>

            <div x-show="tab === 'mail'" x-cloak x-effect="if (tab === 'mail') $wire.loadMail()" class="ui-mt-3">
                @if (! $mailLoaded)
                    <p class="ui-hint" role="status">{{ __('domain_check.loading') }}</p>
                @else
                    @foreach (['spf', 'dkim', 'dmarc'] as $record)
                        @php($row = $mail[$record] ?? null)
                        @php($ok = $row !== null && $row['status'] === \App\Enums\EmailCheckStatus::Ok)
                        <h4 class="ui-form-section">{{ __('domain_check.mail.'.$record.'.title') }}</h4>
                        @if ($row === null || $row['status'] === \App\Enums\EmailCheckStatus::Unreadable)
                            <p class="ui-hint">{{ __('domain_check.unreadable') }}</p>
                        @else
                            @if ($row['record'] !== null)
                                <ul class="ui-mailboxes">
                                    <li><span class="ui-mailbox-address">{{ $row['record'] }}</span></li>
                                </ul>
                            @endif
                            @if ($ok && $row['reason'] === null)
                                <p class="ui-hint">{{ __('domain_check.mail.ok') }}</p>
                            @elseif ($ok)
                                <p class="ui-hint">{{ __('domain_check.mail.'.$record.'.'.$row['reason']) }}</p>
                            @else
                                <p class="ui-note" role="alert">{{ __('domain_check.mail.'.$record.'.'.$row['reason']) }}</p>
                            @endif
                        @endif
                    @endforeach
                @endif
            </div>

            <p x-show="tab === 'ssl'" x-cloak class="ui-note ui-mt-3">{{ __('domain_check.coming_soon') }}</p>
        </div>
    @endif

    <x-slot:footer>
        @if ($host !== null)
            <x-ui.button wire:click="refresh" class="ui-mr-auto">{{ __('domain_check.refresh') }}</x-ui.button>
        @endif
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
