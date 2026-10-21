@php
    use App\Enums\UsageLevel;
@endphp

<div class="fi-usage">
    @if ($report === null)
        <p class="fi-usage-note">{{ __('usage.not_linked') }}</p>
    @else
        <p class="fi-usage-updated">
            {{ $report->hasData() ? __('usage.updated_at', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.never_read') }}
        </p>

        @if ($report->failureReason !== null)
            <div class="fi-usage-error" role="alert">
                {{ __('usage.refresh_failed', ['reason' => __('usage.failure.'.$report->failureReason->value)]) }}
                {{ __('servers.connection_'.$report->failureReason->value) }}
                {{ $report->hasData() ? __('usage.showing_old', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.no_data_yet') }}
            </div>
        @elseif (! $report->hasData())
            <div class="fi-usage-error" role="status">{{ __('usage.no_data_yet') }}</div>
        @endif

        @if ($report->sharedDomains > 1)
            <p class="fi-usage-note">{{ __('usage.shared_account', ['count' => $report->sharedDomains]) }}</p>
        @endif

        @if ($report->gauges !== [])
            <div class="fi-usage-gauges">
                @foreach ($report->gauges as $meter)
                    <div class="fi-usage-gauge">
                        <div class="fi-usage-gauge-head">
                            <span class="fi-usage-label">{{ $meter->label }}</span>
                            <span class="fi-usage-value fi-usage-level-{{ $meter->level?->value ?? 'ok' }}">
                                {{ $meter->usageText() }}@if ($meter->percent() !== null) · %{{ $meter->percent() }}@endif
                                @if ($meter->level !== null) · {{ $meter->level->getLabel() }}@endif
                            </span>
                        </div>
                        @if ($meter->percent() !== null)
                            <div class="fi-usage-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $meter->percent()) }}" aria-label="{{ $meter->label }}">
                                <div class="fi-usage-bar-fill fi-usage-level-{{ $meter->level?->value ?? 'ok' }}" style="width: {{ min(100, $meter->percent()) }}%"></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($report->counters !== [])
            <dl class="fi-usage-counters">
                @foreach ($report->counters as $meter)
                    <div class="fi-usage-counter">
                        <dt>{{ $meter->label }}</dt>
                        <dd class="fi-usage-level-{{ $meter->level?->value ?? 'ok' }}">
                            {{ $meter->usageText() }}@if ($meter->level !== null) · {{ $meter->level->getLabel() }}@endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($report->hasData())
            <h4 class="fi-usage-heading">{{ __('usage.mailboxes_heading', ['count' => count($report->mailboxes)]) }}</h4>

            @if ($report->mailboxes === [])
                <p class="fi-usage-note">{{ __('usage.no_mailboxes') }}</p>
            @else
                <ul class="fi-usage-mailboxes">
                    @foreach ($report->mailboxes as $mailbox)
                        <li class="fi-usage-mailbox">
                            <span class="fi-usage-address">{{ $mailbox->address }}</span>
                            <span class="fi-usage-mailbox-value fi-usage-level-{{ $mailbox->meter->level?->value ?? 'ok' }}">
                                {{ $mailbox->meter->usageText() }}@if ($mailbox->meter->percent() !== null) · %{{ $mailbox->meter->percent() }}@endif
                            </span>
                            <span class="fi-usage-mailbox-status">
                                @if ($mailbox->meter->level !== null)
                                    <x-filament::badge :color="$mailbox->meter->level->getColor()">{{ $mailbox->meter->level->getLabel() }}</x-filament::badge>
                                @endif
                                @if ($mailbox->loginSuspended)
                                    <x-filament::badge color="gray">{{ __('usage.login_suspended') }}</x-filament::badge>
                                @endif
                                @if ($mailbox->incomingSuspended)
                                    <x-filament::badge color="gray">{{ __('usage.incoming_suspended') }}</x-filament::badge>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($report->databases !== [])
                <details class="fi-usage-databases">
                    <summary class="fi-usage-heading">{{ __('usage.databases_heading', ['count' => count($report->databases)]) }}</summary>
                    <ul class="fi-usage-mailboxes">
                        @foreach ($report->databases as $database)
                            <li class="fi-usage-mailbox">
                                <span class="fi-usage-address">{{ $database->label }}</span>
                                <span class="fi-usage-mailbox-value">{{ $database->usedText() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        @endif
    @endif
</div>
