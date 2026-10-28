@php
    use App\Enums\CustomerServiceStatus;
    use App\Support\Money;
    use App\Support\Ui\Js;
    use App\Support\Ui\Links;
    use App\Support\Ui\StatusBadges;
    use Illuminate\Support\Carbon;

    $fmt = fn ($date) => Carbon::parse($date)->format('d.m.Y');
    $primary = $customer->contacts->firstWhere('is_primary', true);

    $serviceColumns = [
        ['key' => 'name', 'label' => __('customer_services.name')],
        ['key' => 'end_date', 'label' => __('customer_services.end_date'), 'from' => 'md'],
        ['key' => 'price', 'label' => __('customer_services.price'), 'end' => true],
        ['key' => 'status', 'label' => __('customer_services.status'), 'from' => 'xl'],
    ];

    if (! $canSeeMoney) {
        $serviceColumns = array_values(array_filter($serviceColumns, fn (array $column): bool => $column['key'] !== 'price'));
    }
@endphp

<div>
    {{-- Header strip --}}
    <section class="ui-strip ui-pane" aria-label="{{ $customer->short_name }}">
        <div class="ui-strip-title">
            <x-ui.avatar :name="$customer->short_name" :id="$customer->id" />
            <div class="min-w-0">
                <div class="ui-crumbs">
                    <a href="{{ route('app.customers.index') }}" wire:navigate.hover>{{ __('customers.plural') }}</a>
                    / {{ $customer->short_name }}
                </div>
                <h1>{{ $customer->short_name }}</h1>
                @if (filled($customer->legal_title))
                    <p class="ui-subtitle is-tight">{{ $customer->legal_title }}</p>
                @endif
                @if ($customer->isArchived())
                    <x-ui.badge class="mt-1">{{ __('archive.status_archived') }}</x-ui.badge>
                @endif
            </div>
        </div>

        @if ($canSeeMoney)
            <dl class="ui-amounts">
                @foreach ([['label' => __('customers.open_debt'), 'totals' => $openDebt], ['label' => __('customers.credit_balance'), 'totals' => $creditBalance]] as $amount)
                    <div class="ui-amount">
                        <dt>{{ $amount['label'] }}</dt>
                        @forelse ($amount['totals'] as $money)
                            <dd><x-ui.money :amount="$money->amount" :currency="$money->currency" /></dd>
                        @empty
                            <dd>{{ __('money.empty') }}</dd>
                        @endforelse
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="ui-strip-actions">
            @unless ($customer->isArchived())
                @can('money.manage')
                    <x-ui.button x-on:click="{!! Js::openWindow('record-payment', ['customer' => $customer->id, 'fixed' => true]) !!}">{{ __('payments.record') }}</x-ui.button>
                @endcan
                @can('services.manage')
                    <x-ui.button x-on:click="{!! Js::openWindow('assign-service', ['customer' => $customer->id]) !!}">{{ __('customer_services.assign') }}</x-ui.button>
                @endcan
            @endunless
            <x-ui.button x-on:click="{!! Js::openWindow('edit-customer', ['customer' => $customer->id]) !!}">{{ __('customers.edit') }}</x-ui.button>
            @if (filled($customer->pcloud_url))
                <x-ui.tooltip :label="__('customers.pcloud_open')">
                    <a href="{{ $customer->pcloud_url }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-icon ui-pcloud" aria-label="{{ __('customers.pcloud_open') }}">@include('livewire.pages.customers.partials.pcloud-icon')</a>
                </x-ui.tooltip>
            @endif
        </div>
    </section>

    <div class="ui-card-grid">
        {{-- Work column --}}
        <div class="ui-stack">
            {{-- Services --}}
            <x-ui.card tag="section" flush>
                <div class="ui-card-head">
                    <h2>{{ __('customer_services.plural') }}</h2>
                    <span class="ui-muted">{{ __('customer_services.active_count', ['count' => $activeServices->count()]) }}</span>
                </div>

                @if ($activeServices->isEmpty())
                    <x-ui.empty-state :heading="__('customer_services.empty_heading')" :description="__('customer_services.empty_description')">
                            @if (! $customer->isArchived() && auth()->user()?->can('services.manage'))
                                <x-ui.button x-on:click="{!! Js::openWindow('assign-service', ['customer' => $customer->id]) !!}">{{ __('customer_services.assign') }}</x-ui.button>
                            @endif
                        </x-ui.empty-state>
                @else
                    <x-ui.table :columns="$serviceColumns" :card="false">
                        @foreach ($activeServices as $service)
                            @include('livewire.pages.customers.partials.service-row', ['service' => $service, 'summary' => $summaries[$service->id] ?? [], 'leadDays' => $leadDays])
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            @if ($cancelledServices->isNotEmpty())
                <x-ui.card tag="details" flush class="ui-details">
                    <summary>{{ __('customers.cancelled_services', ['count' => $cancelledServices->count()]) }}</summary>
                    <x-ui.table :columns="$serviceColumns" :end="true" :card="false">
                        @foreach ($cancelledServices as $service)
                            @include('livewire.pages.customers.partials.service-row', ['service' => $service, 'summary' => $summaries[$service->id] ?? [], 'leadDays' => $leadDays])
                        @endforeach
                    </x-ui.table>
                </x-ui.card>
            @endif

            @if ($canSeeMoney)
            {{-- Open charges --}}
            <x-ui.card tag="section" flush>
                <div class="ui-card-head">
                    <h2>{{ __('charges.open_heading') }}</h2>
                    <a href="{{ Links::customerCharges($customer) }}">{{ __('charges.view_all') }}</a>
                </div>

                @if ($customer->charges->isEmpty())
                    <x-ui.empty-state :heading="__('charges.open_empty_heading')" :description="__('charges.open_empty_description')" />
                @else
                    <x-ui.table
                        :end="true"
                        :card="false"
                        :columns="[
                            ['key' => 'description', 'label' => __('charges.description')],
                            ['key' => 'due_date', 'label' => __('charges.due_date'), 'from' => 'xl'],
                            ['key' => 'status', 'label' => __('charges.status'), 'from' => 'xl'],
                            ['key' => 'remaining', 'label' => __('charges.remaining'), 'end' => true],
                        ]"
                    >
                        @foreach ($customer->charges as $charge)
                            @php
                                $tone = StatusBadges::chargeTone($charge);
                                $domains = $charge->customerService?->domains() ?? [];
                            @endphp
                            <x-ui.row wire:key="charge-{{ $charge->id }}">
                                <td>
                                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-charge', ['charge' => $charge->id]) !!}">{{ $charge->description }}</x-ui.link-button>
                                    @if ($domains !== [])
                                        <span class="ui-sub-line">{{ implode(', ', $domains) }}</span>
                                    @endif
                                    @if ($charge->reminder_sent_at !== null)
                                        <span class="ui-sub-line">{{ __('reminders.sent_on', ['date' => $charge->reminder_sent_at->format('d.m.Y')]) }}</span>
                                    @endif
                                    <span class="ui-sub-line ui-below-xl">{{ __('charges.due_date') }}: {{ $fmt($charge->due_date) }}</span>
                                </td>
                                <td class="ui-from-xl">{{ $fmt($charge->due_date) }}</td>
                                <td class="ui-from-xl">
                                    @if ($tone)
                                        <x-ui.badge :tone="$tone">{{ StatusBadges::chargeLabel($charge) }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="is-end">
                                    <x-ui.money :amount="$charge->remainingAmount()" :currency="$charge->currency" />
                                    @if ($charge->isPartiallyPaid())
                                        <span class="ui-sub-line">{{ __('charges.original_amount.'.$charge->currency->value, ['amount' => (new Money($charge->amount, $charge->currency))->format()]) }}</span>
                                    @endif
                                    @if ($tone)
                                        <span class="ui-below-xl"><x-ui.badge :tone="$tone">{{ StatusBadges::chargeLabel($charge) }}</x-ui.badge></span>
                                    @endif
                                </td>
                                <x-slot:actions>
                                    <x-ui.button size="sm" x-on:click="{!! Js::openWindow('record-payment', ['customer' => $customer->id, 'currency' => $charge->currency->value, 'fixed' => true, 'charge' => $charge->id]) !!}">{{ __('payments.record') }}</x-ui.button>
                                    @if ($whatsAppLinks[$charge->id] ?? null)
                                        <x-ui.tooltip :label="__('reminders.whatsapp.label')" class="ui-from-md">
                                            <a href="{{ $whatsAppLinks[$charge->id] }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-icon ui-btn-sm ui-whatsapp" aria-label="{{ __('reminders.whatsapp.label') }}">@include('livewire.pages.customers.partials.whatsapp-icon')</a>
                                        </x-ui.tooltip>
                                    @endif
                                </x-slot:actions>
                            </x-ui.row>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            {{-- Recent payments --}}
            <x-ui.card tag="section" flush>
                <div class="ui-card-head">
                    <h2>{{ __('payments.recent_heading') }}</h2>
                    <a href="{{ Links::customerPayments($customer) }}">{{ __('payments.view_all') }}</a>
                </div>

                @if ($customer->payments->isEmpty())
                    <x-ui.empty-state :heading="__('payments.recent_empty_heading')" :description="__('payments.recent_empty_description')">
                            @unless ($customer->isArchived())
                                <x-ui.button x-on:click="{!! Js::openWindow('record-payment', ['customer' => $customer->id, 'fixed' => true]) !!}">{{ __('payments.record') }}</x-ui.button>
                            @endunless
                        </x-ui.empty-state>
                @else
                    <x-ui.table
                        :end="true"
                        :card="false"
                        :columns="[
                            ['key' => 'paid_on', 'label' => __('payments.paid_on')],
                            ['key' => 'amount', 'label' => __('payments.amount'), 'end' => true],
                            ['key' => 'method', 'label' => __('payments.payment_method'), 'from' => 'md'],
                            ['key' => 'note', 'label' => __('payments.note'), 'from' => 'lg'],
                        ]"
                    >
                        @foreach ($customer->payments as $payment)
                            <x-ui.row wire:key="payment-{{ $payment->id }}">
                                <td>{{ $fmt($payment->paid_on) }}</td>
                                <td class="is-end">
                                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('view-payment', ['payment' => $payment->id]) !!}"><x-ui.money :amount="$payment->amount" :currency="$payment->currency" /></x-ui.link-button>
                                    @if ($payment->domains() !== [])
                                        <span class="ui-sub-line">{{ implode(', ', $payment->domains()) }}</span>
                                    @endif
                                </td>
                                <td class="ui-from-md">{{ $payment->paymentMethod?->name }}</td>
                                <td class="ui-from-lg">{{ filled($payment->note) ? $payment->note : __('money.empty') }}</td>
                                <x-slot:actions>
                                    <x-ui.button variant="danger" size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-payment', 'id' => $payment->id]) !!}">{{ __('payments.delete') }}</x-ui.button>
                                </x-slot:actions>
                            </x-ui.row>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            {{-- Quotes --}}
            @if ($customer->quotes->isNotEmpty())
                <x-ui.card tag="section" flush>
                    <div class="ui-card-head"><h2>{{ __('quotes.card_heading') }}</h2></div>
                    <x-ui.table
                        :card="false"
                        :columns="[
                            ['key' => 'number', 'label' => __('quotes.column.number')],
                            ['key' => 'total', 'label' => __('quotes.column.total'), 'end' => true],
                            ['key' => 'status', 'label' => __('quotes.column.status')],
                        ]"
                    >
                        @foreach ($customer->quotes as $quote)
                            @php $quoteTone = StatusBadges::quoteTone($quote); @endphp
                            <x-ui.row wire:key="quote-{{ $quote->id }}">
                                <td><x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('quote', ['quote' => $quote->id]) !!}">{{ $quote->number }}</x-ui.link-button></td>
                                <td class="is-end">
                                    @forelse ($quote->totals() as $money)
                                        <div><x-ui.money :amount="$money->amount" :currency="$money->currency" /></div>
                                    @empty
                                        {{ __('quotes.pdf.free') }}
                                    @endforelse
                                </td>
                                <td>
                                    @if ($quoteTone)
                                        <x-ui.badge :tone="$quoteTone">{{ StatusBadges::quoteLabel($quote) }}</x-ui.badge>
                                    @else
                                        {{ StatusBadges::quoteLabel($quote) }}
                                    @endif
                                </td>
                            </x-ui.row>
                        @endforeach
                    </x-ui.table>
                </x-ui.card>
            @endif
            @endif
        </div>

        {{-- Information column --}}
        <div class="ui-stack">
            @if ($domains !== [])
                <x-ui.card tag="section" class="ui-domains-card">
                    <div class="ui-card-head">
                        <h2>{{ __('customer_services.domains_heading') }}</h2>
                        <span class="ui-muted">{{ __('customer_services.domains_count', ['count' => count($domains)]) }}</span>
                    </div>
                    <x-ui.domains :domains="$domains" :cloudflare="$cloudflareDomains" />
                </x-ui.card>
            @endif

            @can('tasks.manage')
                <x-ui.card tag="section">
                    <div class="ui-card-head">
                        <h2>{{ __('tasks.plural') }}</h2>
                        @unless ($customer->isArchived())
                            <x-ui.button size="sm" x-on:click="{!! Js::openWindow('edit-task', ['customer' => $customer->id]) !!}">{{ __('tasks.add') }}</x-ui.button>
                        @endunless
                    </div>

                    @forelse ($openTasks as $task)
                        @php
                            $dueBadge = match (true) {
                                $task->isOverdue() => ['red', __('tasks.overdue_days', ['count' => (int) $task->due_date->diffInDays(today(), true)])],
                                $task->isDueToday() => ['amber', __('tasks.due_today')],
                                default => null,
                            };
                        @endphp
                        <div class="ui-contact" wire:key="task-{{ $task->id }}">
                            <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-task', ['task' => $task->id]) !!}">{{ $task->title }}</x-ui.link-button>
                            <span class="ui-muted">
                                {{ $task->assignee->name }}
                                @unless ($task->assignee->isActive())
                                    {{ __('tasks.inactive_suffix') }}
                                @endunless
                                @if ($task->due_date)
                                    · {{ $fmt($task->due_date) }}
                                @endif
                            </span>
                            @if ($dueBadge)
                                <span><x-ui.badge :tone="$dueBadge[0]">{{ $dueBadge[1] }}</x-ui.badge></span>
                            @endif
                            <span><x-ui.button size="sm" wire:click="completeTask({{ $task->id }})">{{ __('tasks.complete') }}</x-ui.button></span>
                        </div>
                    @empty
                        <p class="ui-muted ui-m-0">{{ __('tasks.card_empty') }}</p>
                    @endforelse

                    <p class="ui-m-0 mt-3"><a href="{{ Links::customerTasks($customer) }}">{{ __('tasks.view_all') }}</a></p>
                </x-ui.card>
            @endcan

            @can('calendar.manage')
                <x-ui.card tag="section">
                    <div class="ui-card-head">
                        <h2>{{ __('calendar.card.heading') }}</h2>
                        @unless ($customer->isArchived())
                            <x-ui.button size="sm" x-on:click="{!! Js::openWindow('edit-calendar-event', ['customer' => $customer->id]) !!}">{{ __('calendar.add') }}</x-ui.button>
                        @endunless
                    </div>

                    @forelse ($upcomingEvents as $upcoming)
                        <div class="ui-contact" wire:key="event-{{ $upcoming['event']->id }}">
                            <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-calendar-event', ['event' => $upcoming['event']->id]) !!}">{{ $upcoming['event']->title }}</x-ui.link-button>
                            <span class="ui-muted">
                                {{ $fmt($upcoming['date']) }}
                                · {{ $upcoming['event']->all_day ? __('calendar.all_day') : substr((string) $upcoming['event']->start_time, 0, 5) }}
                            </span>
                        </div>
                    @empty
                        <p class="ui-muted ui-m-0">{{ __('calendar.card.empty') }}</p>
                    @endforelse

                    <p class="ui-m-0 mt-3"><a href="{{ route('app.calendar', ['view' => 'list']) }}">{{ __('calendar.card.view_all') }}</a></p>
                </x-ui.card>
            @endcan

            <x-ui.card tag="section">
                <div class="ui-card-head">
                    <h2>{{ __('contacts.plural') }}</h2>
                    <x-ui.button size="sm" x-on:click="{!! Js::openWindow('contact', ['customer' => $customer->id]) !!}">{{ __('contacts.add') }}</x-ui.button>
                </div>

                @forelse ($customer->contacts as $contact)
                    <div class="ui-contact" wire:key="contact-{{ $contact->id }}">
                        <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('contact', ['customer' => $customer->id, 'contact' => $contact->id]) !!}">{{ $contact->name.($contact->is_primary ? ' · '.__('contacts.is_primary') : '') }}</x-ui.link-button>
                        @if (filled($contact->title))
                            <span class="ui-muted">{{ $contact->title }}</span>
                        @endif
                        @if (filled($contact->phone))
                            <a href="tel:{{ $contact->phone }}"><x-heroicon-o-phone aria-hidden="true" />{{ $contact->phone }}</a>
                        @endif
                        @if (filled($contact->email))
                            <a href="mailto:{{ $contact->email }}"><x-heroicon-o-envelope aria-hidden="true" />{{ $contact->email }}</a>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state :heading="__('contacts.empty_heading')" :description="__('contacts.empty_description')" />
                @endforelse
            </x-ui.card>

            <x-ui.card tag="section">
                <div class="ui-card-head"><h2>{{ __('customers.billing_section') }}</h2></div>
                <dl class="ui-kv">
                    @foreach (['legal_title', 'tax_office', 'tax_number', 'billing_address'] as $field)
                        <dt>{{ __('customers.'.$field) }}</dt>
                        <dd>{{ filled($customer->{$field}) ? $customer->{$field} : __('money.empty') }}</dd>
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card tag="section">
                <div class="ui-card-head"><h2>{{ __('customers.notes_section') }}</h2></div>
                <p class="ui-m-0 ui-pre-line">{{ filled($customer->notes) ? $customer->notes : __('money.empty') }}</p>
            </x-ui.card>
        </div>
    </div>

    <div class="ui-danger-row">
        @if ($customer->isArchived())
            <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'unarchive-customer', 'id' => $customer->id]) !!}">{{ __('archive.unarchive') }}</x-ui.button>
        @else
            <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'archive-customer', 'id' => $customer->id]) !!}">{{ __('archive.archive') }}</x-ui.button>
        @endif
        @unless ($inUse)
            <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-customer', 'id' => $customer->id]) !!}">{{ __('customers.delete') }}</x-ui.button>
        @endunless
    </div>
</div>
