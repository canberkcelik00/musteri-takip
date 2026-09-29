<x-filament-widgets::widget>
    <x-filament::section :heading="__('today.tasks_heading')">
        @if ($isEmpty)
            <p class="fi-ta-text text-sm text-gray-500 dark:text-gray-400">
                {{ __('today.empty') }}
            </p>
        @else
            <div class="flex flex-col gap-y-6">
                @if ($overdue['total'] > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('today.section_overdue') }}
                        </h3>

                        <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($overdue['items'] as $charge)
                                <li class="flex items-center justify-between gap-x-4 py-2">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $this->customerUrl($charge->customer) }}" class="fi-link text-sm font-medium text-primary-600 dark:text-primary-400">
                                            {{ $charge->customer->short_name }}
                                        </a>
                                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $charge->description }}</p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <p class="text-sm font-medium tabular-nums text-gray-950 dark:text-white">{{ $this->chargeAmount($charge) }}</p>
                                        <x-filament::badge color="danger">{{ $this->chargeStatusLabel($charge) }}</x-filament::badge>
                                    </div>

                                    @unless ($charge->customer->isArchived())
                                        {{ $this->recordPaymentAction()->arguments(['customer' => $charge->customer_id, 'currency' => $charge->currency->value]) }}
                                    @endunless
                                </li>
                            @endforeach
                        </ul>

                        @if ($overdue['total'] > count($overdue['items']))
                            <a href="{{ $overdueUrl }}" class="fi-link mt-2 inline-block text-sm font-medium text-primary-600 dark:text-primary-400">
                                {{ __('today.view_all', ['count' => $overdue['total']]) }}
                            </a>
                        @endif
                    </div>
                @endif

                @if ($due_soon['total'] > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('today.section_due_soon') }}
                        </h3>

                        <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($due_soon['items'] as $charge)
                                <li class="flex items-center justify-between gap-x-4 py-2">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $this->customerUrl($charge->customer) }}" class="fi-link text-sm font-medium text-primary-600 dark:text-primary-400">
                                            {{ $charge->customer->short_name }}
                                        </a>
                                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $charge->description }}</p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <p class="text-sm font-medium tabular-nums text-gray-950 dark:text-white">{{ $this->chargeAmount($charge) }}</p>
                                        <x-filament::badge color="warning">{{ $this->chargeStatusLabel($charge) }}</x-filament::badge>
                                    </div>

                                    @unless ($charge->customer->isArchived())
                                        {{ $this->recordPaymentAction()->arguments(['customer' => $charge->customer_id, 'currency' => $charge->currency->value]) }}
                                    @endunless
                                </li>
                            @endforeach
                        </ul>

                        @if ($due_soon['total'] > count($due_soon['items']))
                            <a href="{{ $dueSoonUrl }}" class="fi-link mt-2 inline-block text-sm font-medium text-primary-600 dark:text-primary-400">
                                {{ __('today.view_all', ['count' => $due_soon['total']]) }}
                            </a>
                        @endif
                    </div>
                @endif

                @if ($free_decisions['total'] > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('today.section_free_decisions') }}
                        </h3>

                        <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($free_decisions['items'] as $service)
                                <li class="flex items-center justify-between gap-x-4 py-2">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $this->customerUrl($service->customer) }}" class="fi-link text-sm font-medium text-primary-600 dark:text-primary-400">
                                            {{ $service->customer->short_name }}
                                        </a>
                                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $service->name }} · {{ $service->end_date->format('d.m.Y') }}</p>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-x-2">
                                        {{ $this->confirmFreeContinuationAction()->arguments(['customerService' => $service->id]) }}
                                        {{ $this->removeFreeAction()->arguments(['customerService' => $service->id]) }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        @if ($free_decisions['row_total'] > count($free_decisions['items']))
                            <a href="{{ $freeDecisionsUrl }}" class="fi-link mt-2 inline-block text-sm font-medium text-primary-600 dark:text-primary-400">
                                {{ __('today.view_all', ['count' => $free_decisions['total']]) }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
