@php
    use App\Support\ByteSize;
    use App\Support\Ui\StatusBadges;

    $tone = fn ($level) => $level === null ? null : StatusBadges::toneOfColor($level->getColor());
@endphp

<x-ui.window :title="$server->name">
    <div class="ui-usage">
        <p class="ui-hint" style="margin: -6px 0 8px; text-align: center">{{ $server->hostname }}</p>

        @if ($report === null)
            <p class="ui-note">{{ __('usage.server_not_whm') }}</p>
        @else
            <p class="ui-hint">
                {{ $report->hasData() ? __('usage.updated_at', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.never_read') }}
            </p>

            @if ($report->failureReason !== null)
                <div class="ui-error-box" role="alert">
                    {{ __('usage.refresh_failed', ['reason' => __('usage.failure.'.$report->failureReason->value)]) }}
                    {{ __('servers.connection_'.$report->failureReason->value) }}
                    {{ $report->hasData() ? __('usage.showing_old', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.server_no_data_yet') }}
                </div>
            @elseif (! $report->hasData())
                <div class="ui-note" role="status">{{ __('usage.server_no_data_yet') }}</div>
            @endif

            @if ($report->hasData())
                @unless ($report->limitsRead)
                    <p class="ui-note">{{ __('usage.reseller_limits_unknown') }}</p>
                @endunless

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

                <dl class="ui-counters">
                    <div><dt>{{ __('usage.server_accounts_active') }}</dt><dd>{{ $report->activeCount }}</dd></div>
                    <div><dt>{{ __('usage.server_accounts_suspended') }}</dt><dd>{{ $report->suspendedCount }}</dd></div>
                    <div><dt>{{ __('usage.server_accounts_linked') }}</dt><dd>{{ $report->linkedCount }}</dd></div>
                    <div><dt>{{ __('usage.server_accounts_unlinked') }}</dt><dd>{{ $report->unlinkedCount }}</dd></div>
                    <div>
                        <dt>{{ __('usage.allocated_disk') }}</dt>
                        <dd>
                            {{ ByteSize::format($report->allocatedDisk) }}@if ($report->resellerDiskLimit !== null) · {{ __('usage.allocated_of_limit', ['limit' => ByteSize::format($report->resellerDiskLimit)]) }}@endif
                            @if ($report->unlimitedDiskAccounts > 0) · {{ __('usage.allocated_unlimited', ['count' => $report->unlimitedDiskAccounts]) }}@endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('usage.allocated_bandwidth') }}</dt>
                        <dd>
                            {{ ByteSize::format($report->allocatedBandwidth) }}@if ($report->resellerBandwidthLimit !== null) · {{ __('usage.allocated_of_limit', ['limit' => ByteSize::format($report->resellerBandwidthLimit)]) }}@endif
                            @if ($report->unlimitedBandwidthAccounts > 0) · {{ __('usage.allocated_unlimited', ['count' => $report->unlimitedBandwidthAccounts]) }}@endif
                        </dd>
                    </div>
                </dl>

                <h4 class="ui-form-section" style="margin-top: 14px">{{ __('usage.accounts_heading', ['count' => count($report->accounts)]) }}</h4>

                @if ($report->accounts === [])
                    <p class="ui-hint">{{ __('usage.no_accounts') }}</p>
                @else
                    <ul class="ui-mailboxes">
                        @foreach ($report->accounts as $account)
                            <li>
                                <span class="ui-mailbox-address">
                                    {{ $account->domain !== '' ? $account->domain : $account->username }}
                                    <span class="ui-sub-line">{{ $account->username }}@if ($account->plan !== null) · {{ $account->plan }}@endif</span>
                                    @forelse ($account->customers as $customer)
                                        <span class="ui-sub-line">
                                            <a href="{{ route('app.customers.show', $customer['id']) }}" class="ui-link-button">{{ $customer['short_name'] }}</a>@if ($customer['archived']) · {{ __('usage.customer_archived') }}@endif
                                        </span>
                                    @empty
                                        <span class="ui-sub-line">{{ __('usage.not_linked_short') }}</span>
                                    @endforelse
                                </span>
                                @foreach ([['meter' => $account->disk, 'label' => __('usage.resource.disk_usage')], ['meter' => $account->bandwidth, 'label' => __('usage.resource.bandwidth')]] as $cell)
                                    <span>
                                        <span class="ui-sub-line">{{ $cell['label'] }}</span>
                                        @if ($cell['meter'] === null)
                                            {{ __('money.empty') }}
                                        @else
                                            <span class="ui-level-{{ $cell['meter']->level?->value ?? 'ok' }}">
                                                {{ $cell['meter']->usageText() }}@if ($cell['meter']->percent() !== null) · %{{ $cell['meter']->percent() }}@endif
                                            </span>
                                        @endif
                                    </span>
                                @endforeach
                                <span class="ui-mailbox-status">
                                    @if ($account->suspended)
                                        <x-ui.badge>{{ __('usage.account_suspended') }}</x-ui.badge>
                                    @endif
                                    @if ($account->level() !== null)
                                        <x-ui.badge :tone="$tone($account->level())">{{ $account->level()->getLabel() }}</x-ui.badge>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        @endif
    </div>

    <x-slot:footer>
        <x-ui.button wire:click="refreshUsage" style="margin-right: auto">{{ __('usage.refresh') }}</x-ui.button>
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
