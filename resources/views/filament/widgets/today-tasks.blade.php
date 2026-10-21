<x-filament-widgets::widget>
    <div class="fi-ta-ctn fi-today-tasks">
        <div class="fi-ta-main">
            <div class="fi-today-tasks-header">
                <h3 class="fi-today-tasks-heading">{{ __('today.tasks_heading') }}</h3>

                @unless ($isEmpty)
                    <span class="fi-today-tasks-count">{{ __('today.tasks_count', ['count' => $taskCount]) }}</span>
                @endunless
            </div>

            @if ($isEmpty)
                <p class="fi-today-tasks-empty">{{ __('today.empty') }}</p>
            @else
                <div class="fi-ta-content-ctn">
                    <div class="fi-ta-content">
                        <table class="fi-ta-table">
                            <thead>
                                <tr>
                                    <th class="fi-ta-header-cell">{{ __('today.col_customer') }}</th>
                                    <th class="fi-ta-header-cell fi-cell-from-md">{{ __('today.col_subject') }}</th>
                                    <th class="fi-ta-header-cell fi-cell-from-md">{{ __('today.col_due') }}</th>
                                    <th class="fi-ta-header-cell fi-cell-from-md">{{ __('today.col_status') }}</th>
                                    <th class="fi-ta-header-cell fi-align-end">{{ __('today.col_amount') }}</th>
                                    <th class="fi-ta-header-cell"></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">
                                            <a href="{{ $this->customerUrl($row['customer']) }}" class="fi-today-customer">{{ $row['customer']->short_name }}</a>
                                            @if (filled($row['customer']->legal_title))
                                                <p class="fi-today-customer-title">{{ $row['customer']->legal_title }}</p>
                                            @endif
                                            {{-- Below `md` the "Durum" column is hidden; the state moves under the name. --}}
                                            <div class="fi-mobile-badge">
                                                @if ($row['color'] !== null)
                                                    <x-filament::badge :color="$row['color']">{{ $row['status'] }}</x-filament::badge>
                                                @else
                                                    {{ $row['status'] }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell fi-cell-from-md">{{ $row['subject'] }}</td>
                                        <td class="fi-ta-cell fi-cell-from-md">{{ $row['date'] }}</td>
                                        <td class="fi-ta-cell fi-cell-from-md">
                                            @if ($row['color'] !== null)
                                                <x-filament::badge :color="$row['color']">{{ $row['status'] }}</x-filament::badge>
                                            @else
                                                {{ $row['status'] }}
                                            @endif
                                        </td>
                                        <td class="fi-ta-cell fi-money">{{ $row['amount'] }}</td>
                                        <td class="fi-ta-cell fi-today-actions">
                                            @if ($row['type'] === 'charge')
                                                @unless ($row['customer']->isArchived())
                                                    {{ ($this->recordPaymentAction())(['customer' => $row['charge']->customer_id, 'currency' => $row['charge']->currency->value]) }}
                                                @endunless
                                            @elseif ($row['type'] === 'quota')
                                                {{-- Until the "Kullanım" window (G-9.4) the button opens the customer's card. --}}
                                                <x-filament::button tag="a" :href="$this->customerUrl($row['customer'])" color="gray" size="sm">{{ __('today.view_usage') }}</x-filament::button>
                                            @else
                                                {{ ($this->confirmFreeContinuationAction())(['customerService' => $row['service']->id]) }}
                                                {{ ($this->removeFreeAction())(['customerService' => $row['service']->id]) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($overdue['total'] > count($overdue['items']) || $due_soon['total'] > count($due_soon['items']) || $free_decisions['row_total'] > count($free_decisions['items']) || $quota['total'] > count($quota['items']))
                    <div class="fi-today-more">
                        @if ($overdue['total'] > count($overdue['items']))
                            <a href="{{ $overdueUrl }}" class="fi-link">{{ __('today.section_overdue') }}: {{ __('today.view_all', ['count' => $overdue['total']]) }}</a>
                        @endif

                        @if ($due_soon['total'] > count($due_soon['items']))
                            <a href="{{ $dueSoonUrl }}" class="fi-link">{{ __('today.section_due_soon') }}: {{ __('today.view_all', ['count' => $due_soon['total']]) }}</a>
                        @endif

                        @if ($free_decisions['row_total'] > count($free_decisions['items']))
                            <a href="{{ $freeDecisionsUrl }}" class="fi-link">{{ __('today.section_free_decisions') }}: {{ __('today.view_all', ['count' => $free_decisions['total']]) }}</a>
                        @endif

                        @if ($quota['total'] > count($quota['items']))
                            <span>{{ __('today.section_quota') }}: {{ __('today.quota_more', ['count' => $quota['total'] - count($quota['items'])]) }}</span>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
