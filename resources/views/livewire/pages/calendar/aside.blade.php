{{-- The day view's side column: a small month, the selected event's detail, and what was recorded that day. --}}
@php
    use App\Support\Avatar;
    use App\Support\Ui\Js;
@endphp
<aside class="cal-aside">
    <div class="ui-pane cal-mini">
        <div class="cal-mini-head">
            <strong>{{ $miniTitle }}</strong>
            <div class="cal-mini-nav">
                <button type="button" class="ui-btn ui-btn-icon ui-btn-sm" wire:click="stepMiniMonth(-1)" aria-label="{{ __('calendar.mini_previous') }}"><x-heroicon-m-chevron-left class="cal-icon" aria-hidden="true" /></button>
                <button type="button" class="ui-btn ui-btn-icon ui-btn-sm" wire:click="stepMiniMonth(1)" aria-label="{{ __('calendar.mini_next') }}"><x-heroicon-m-chevron-right class="cal-icon" aria-hidden="true" /></button>
            </div>
        </div>
        <div class="cal-mini-grid cal-mini-weekdays" aria-hidden="true">
            @for ($weekday = 1; $weekday <= 7; $weekday++)
                <span>{{ __('calendar.weekdays_min.'.$weekday) }}</span>
            @endfor
        </div>
        <div class="cal-mini-grid">
            @foreach ($mini as $day)
                <button type="button" wire:key="mini-{{ $day['date']->toDateString() }}" wire:click="pickDay('{{ $day['date']->toDateString() }}')" @class(['is-out' => ! $day['inMonth'], 'is-picked' => $day['picked']]) @if ($day['picked']) aria-current="date" @endif>{{ $day['number'] }}</button>
            @endforeach
        </div>
    </div>

    @if ($detail !== null)
        @php
            /** @var \App\Models\CalendarEvent $event */
            $event = $detail['event'];
        @endphp
        <div class="ui-pane cal-detail" wire:key="detail-{{ $selected }}">
            <div class="cal-detail-kind"><span class="cal-dot cal-c-blue" aria-hidden="true"></span>{{ __('calendar.detail.kind') }}</div>
            <h2>{{ $event->title }}</h2>
            <p class="cal-detail-when">{{ $detail['when'] }}</p>
            <dl>
                @if ($event->customer)
                    <div class="cal-detail-row"><dt>{{ __('calendar.detail.customer') }}</dt><dd><a href="{{ route('app.customers.show', $event->customer) }}" wire:navigate>{{ $event->customer->short_name }}</a></dd></div>
                @endif
                @if ($event->location)
                    <div class="cal-detail-row"><dt>{{ __('calendar.detail.location') }}</dt><dd>{{ $event->location }}</dd></div>
                @endif
                @if ($event->attendees->isNotEmpty())
                    <div class="cal-detail-row"><dt>{{ __('calendar.detail.attendees') }}</dt>
                        <dd class="cal-people">
                            @foreach ($event->attendees as $person)
                                <span><x-ui.avatar :name="$person->name" :id="$person->id" class="cal-avatar" />{{ $person->name }}</span>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @if ($detail['reminder'])
                    <div class="cal-detail-row"><dt>{{ __('calendar.detail.reminder') }}</dt><dd>{{ $detail['reminder'] }}</dd></div>
                @endif
                @if ($detail['repeat'])
                    <div class="cal-detail-row"><dt>{{ __('calendar.detail.repeat') }}</dt><dd>{{ $detail['repeat'] }}</dd></div>
                @endif
                @if ($event->notes)
                    <div class="cal-detail-row"><dt>{{ __('calendar.detail.notes') }}</dt><dd class="cal-notes">{{ $event->notes }}</dd></div>
                @endif
            </dl>
            <div class="cal-detail-actions">
                @if ($canManage)
                    <x-ui.button x-on:click="{!! Js::openWindow('edit-calendar-event', ['event' => $event->id]) !!}">{{ __('calendar.detail.edit') }}</x-ui.button>
                @endif
                @if ($event->customer)
                    <x-ui.button :href="route('app.customers.show', $event->customer)" wire:navigate>{{ __('calendar.detail.customer_card') }}</x-ui.button>
                @endif
                @if ($canManage)
                    <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-calendar-event', 'id' => $event->id]) !!}">{{ __('calendar.delete') }}</x-ui.button>
                @endif
                <x-ui.button wire:click="closeDetail">{{ __('calendar.detail.close') }}</x-ui.button>
            </div>
        </div>
    @endif

    <div class="ui-pane cal-records">
        <strong>{{ $anchor->isSameDay(today()) ? __('calendar.records_today') : __('calendar.records_day', ['date' => $anchor->format('d.m.Y')]) }}</strong>
        @forelse ($records as $entry)
            <div class="cal-log" wire:key="record-{{ $entry->key }}">
                <time>{{ $entry->start }}</time>
                <span><span class="cal-dot cal-c-{{ $entry->category->color() }}" aria-hidden="true"></span>{{ $entry->title }}@if ($entry->detail) · {{ $entry->detail }}@endif</span>
            </div>
        @empty
            <p class="cal-log-empty">{{ __('calendar.nothing_day') }}</p>
        @endforelse
    </div>
</aside>
