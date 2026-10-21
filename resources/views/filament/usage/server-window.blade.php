@php
    use App\Filament\Resources\Customers\CustomerResource;
    use App\Support\ByteSize;
@endphp

<div class="fi-usage">
    @if ($report === null)
        <p class="fi-usage-note">{{ __('usage.server_not_whm') }}</p>
    @else
        <p class="fi-usage-updated">
            {{ $report->hasData() ? __('usage.updated_at', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.never_read') }}
        </p>

        @if ($report->failureReason !== null)
            <div class="fi-usage-error" role="alert">
                {{ __('usage.refresh_failed', ['reason' => __('usage.failure.'.$report->failureReason->value)]) }}
                {{ __('servers.connection_'.$report->failureReason->value) }}
                {{ $report->hasData() ? __('usage.showing_old', ['at' => $report->refreshedAt->format('d.m.Y H:i')]) : __('usage.server_no_data_yet') }}
            </div>
        @elseif (! $report->hasData())
            <div class="fi-usage-error" role="status">{{ __('usage.server_no_data_yet') }}</div>
        @endif

        @if ($report->hasData())
            @unless ($report->limitsRead)
                <p class="fi-usage-note">{{ __('usage.reseller_limits_unknown') }}</p>
            @endunless

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

            <dl class="fi-usage-counters">
                <div class="fi-usage-counter">
                    <dt>{{ __('usage.server_accounts_active') }}</dt>
                    <dd>{{ $report->activeCount }}</dd>
                </div>
                <div class="fi-usage-counter">
                    <dt>{{ __('usage.server_accounts_suspended') }}</dt>
                    <dd>{{ $report->suspendedCount }}</dd>
                </div>
                <div class="fi-usage-counter">
                    <dt>{{ __('usage.server_accounts_linked') }}</dt>
                    <dd>{{ $report->linkedCount }}</dd>
                </div>
                <div class="fi-usage-counter">
                    <dt>{{ __('usage.server_accounts_unlinked') }}</dt>
                    <dd>{{ $report->unlinkedCount }}</dd>
                </div>
                <div class="fi-usage-counter">
                    <dt>{{ __('usage.allocated_disk') }}</dt>
                    <dd>
                        {{ ByteSize::format($report->allocatedDisk) }}@if ($report->resellerDiskLimit !== null) · {{ __('usage.allocated_of_limit', ['limit' => ByteSize::format($report->resellerDiskLimit)]) }}@endif
                        @if ($report->unlimitedDiskAccounts > 0) · {{ __('usage.allocated_unlimited', ['count' => $report->unlimitedDiskAccounts]) }}@endif
                    </dd>
                </div>
                <div class="fi-usage-counter">
                    <dt>{{ __('usage.allocated_bandwidth') }}</dt>
                    <dd>
                        {{ ByteSize::format($report->allocatedBandwidth) }}@if ($report->resellerBandwidthLimit !== null) · {{ __('usage.allocated_of_limit', ['limit' => ByteSize::format($report->resellerBandwidthLimit)]) }}@endif
                        @if ($report->unlimitedBandwidthAccounts > 0) · {{ __('usage.allocated_unlimited', ['count' => $report->unlimitedBandwidthAccounts]) }}@endif
                    </dd>
                </div>
            </dl>

            <h4 class="fi-usage-heading">{{ __('usage.accounts_heading', ['count' => count($report->accounts)]) }}</h4>

            @if ($report->accounts === [])
                <p class="fi-usage-note">{{ __('usage.no_accounts') }}</p>
            @else
                <div class="fi-usage-account-heads" aria-hidden="true">
                    <span>{{ __('usage.col_account') }}</span>
                    <span>{{ __('usage.col_customer') }}</span>
                    <span>{{ __('usage.resource.disk_usage') }}</span>
                    <span>{{ __('usage.resource.bandwidth') }}</span>
                </div>

                <ul class="fi-usage-accounts">
                    @foreach ($report->accounts as $account)
                        <li class="fi-usage-account">
                            <div>
                                <div class="fi-usage-account-name">{{ $account->domain !== '' ? $account->domain : $account->username }}</div>
                                <div class="fi-usage-account-sub">{{ $account->username }}@if ($account->plan !== null) · {{ $account->plan }}@endif</div>
                                @if ($account->suspended || $account->level() !== null)
                                    <div class="fi-usage-account-badges">
                                        @if ($account->suspended)
                                            <x-filament::badge color="gray" :title="$account->suspendReason">{{ __('usage.account_suspended') }}</x-filament::badge>
                                        @endif
                                        @if ($account->level() !== null)
                                            <x-filament::badge :color="$account->level()->getColor()">{{ $account->level()->getLabel() }}</x-filament::badge>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div>
                                <span class="fi-usage-account-label">{{ __('usage.col_customer') }}</span>
                                @forelse ($account->customers as $customer)
                                    <div>
                                        <a href="{{ CustomerResource::getUrl('view', ['record' => $customer['id']]) }}" class="fi-link">{{ $customer['short_name'] }}</a>@if ($customer['archived']) <span class="fi-usage-account-sub">· {{ __('usage.customer_archived') }}</span>@endif
                                    </div>
                                @empty
                                    <span class="fi-usage-account-sub">{{ __('usage.not_linked_short') }}</span>
                                @endforelse
                            </div>

                            @foreach ([['meter' => $account->disk, 'label' => __('usage.resource.disk_usage')], ['meter' => $account->bandwidth, 'label' => __('usage.resource.bandwidth')]] as $cell)
                                <div>
                                    <span class="fi-usage-account-label">{{ $cell['label'] }}</span>
                                    @if ($cell['meter'] === null)
                                        <span class="fi-usage-account-sub">{{ __('money.empty') }}</span>
                                    @else
                                        <span class="fi-usage-value fi-usage-level-{{ $cell['meter']->level?->value ?? 'ok' }}">
                                            {{ $cell['meter']->usageText() }}@if ($cell['meter']->percent() !== null) · %{{ $cell['meter']->percent() }}@endif
                                        </span>
                                        @if ($cell['meter']->percent() !== null)
                                            <div class="fi-usage-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $cell['meter']->percent()) }}" aria-label="{{ $cell['label'] }}">
                                                <div class="fi-usage-bar-fill fi-usage-level-{{ $cell['meter']->level?->value ?? 'ok' }}" style="width: {{ min(100, $cell['meter']->percent()) }}%"></div>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    @endif
</div>
