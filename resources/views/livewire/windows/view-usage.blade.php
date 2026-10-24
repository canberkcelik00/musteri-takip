@php
    use App\Support\Ui\StatusBadges;

    $tone = fn ($level) => $level === null ? null : StatusBadges::toneOfColor($level->getColor());
@endphp

<x-ui.window :title="$report->title ?? __('usage.heading')">
    <div class="ui-usage">
        @if ($report !== null)
            <p class="ui-hint" style="margin: -6px 0 8px; text-align: center">{{ $report->serverName }}</p>
        @endif

        @if ($report === null)
            <p class="ui-note">{{ __('usage.not_linked') }}</p>
        @else
            <p class="ui-hint">
                {{ $report->hasData() ? __('usage.updated_at', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.never_read') }}
            </p>

            @if ($report->failureReason !== null)
                <div class="ui-error-box" role="alert">
                    {{ __('usage.refresh_failed', ['reason' => __('usage.failure.'.$report->failureReason->value)]) }}
                    {{ __('servers.connection_'.$report->failureReason->value) }}
                    {{ $report->hasData() ? __('usage.showing_old', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.no_data_yet') }}
                </div>
            @elseif (! $report->hasData())
                <div class="ui-note" role="status">{{ __('usage.no_data_yet') }}</div>
            @endif

            @if ($report->sharedDomains > 1)
                <p class="ui-note">{{ __('usage.shared_account', ['count' => $report->sharedDomains]) }}</p>
            @endif

            @if ($report->domains !== [])
                <p class="ui-note">{{ __('usage.other_domains', ['domains' => implode(', ', $report->domains)]) }}</p>
            @endif

            @if ($report->gauges !== [])
                <div class="ui-gauges">
                    @foreach ($report->gauges as $meter)
                        <div>
                            <div class="ui-gauge-head">
                                <span>{{ $meter->label }}</span>
                                <span class="ui-level-{{ $meter->level?->value ?? 'ok' }}">
                                    {{ $meter->usageText() }}@if ($meter->percent() !== null) · %{{ $meter->percent() }}@endif
                                    @if ($meter->level !== null) · {{ $meter->level->getLabel() }}@endif
                                </span>
                            </div>
                            @if ($meter->percent() !== null)
                                <div class="ui-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $meter->percent()) }}" aria-label="{{ $meter->label }}">
                                    <div class="ui-bar-fill ui-level-{{ $meter->level?->value ?? 'ok' }}" style="width: {{ min(100, $meter->percent()) }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($report->counters !== [])
                <dl class="ui-counters">
                    @foreach ($report->counters as $meter)
                        <div>
                            <dt>{{ $meter->label }}</dt>
                            <dd class="ui-level-{{ $meter->level?->value ?? 'ok' }}">{{ $meter->usageText() }}@if ($meter->level !== null) · {{ $meter->level->getLabel() }}@endif</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if ($report->hasData())
                <h4 class="ui-form-section" style="margin-top: 14px">{{ __('usage.mailboxes_heading', ['count' => count($report->mailboxes)]) }}</h4>

                @if ($report->mailboxes === [])
                    <p class="ui-hint">{{ __('usage.no_mailboxes') }}</p>
                @else
                    <ul class="ui-mailboxes">
                        @foreach ($report->mailboxes as $mailbox)
                            <li>
                                <span class="ui-mailbox-address">{{ $mailbox->address }}</span>
                                <span class="ui-level-{{ $mailbox->meter->level?->value ?? 'ok' }}">
                                    {{ $mailbox->meter->usageText() }}@if ($mailbox->meter->percent() !== null) · %{{ $mailbox->meter->percent() }}@endif
                                </span>
                                <span class="ui-mailbox-status">
                                    @if ($mailbox->meter->level !== null)
                                        <x-ui.badge :tone="$tone($mailbox->meter->level)">{{ $mailbox->meter->level->getLabel() }}</x-ui.badge>
                                    @endif
                                    @if ($mailbox->loginSuspended)
                                        <x-ui.badge>{{ __('usage.login_suspended') }}</x-ui.badge>
                                    @endif
                                    @if ($mailbox->incomingSuspended)
                                        <x-ui.badge>{{ __('usage.incoming_suspended') }}</x-ui.badge>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($report->databases !== [])
                    <details style="margin-top: 10px">
                        <summary class="ui-form-section" style="cursor: pointer">{{ __('usage.databases_heading', ['count' => count($report->databases)]) }}</summary>
                        <ul class="ui-mailboxes">
                            @foreach ($report->databases as $database)
                                <li>
                                    <span class="ui-mailbox-address">{{ $database->label }}</span>
                                    <span>{{ $database->usedText() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        @endif
    </div>

    <x-slot:footer>
        @if ($report !== null)
            <x-ui.button wire:click="refreshUsage" style="margin-right: auto">{{ __('usage.refresh') }}</x-ui.button>
        @endif
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
