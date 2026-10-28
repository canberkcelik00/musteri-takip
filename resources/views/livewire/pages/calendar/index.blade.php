@php
    use App\Livewire\Pages\Calendar\Index;
    use App\Support\Ui\Js;
@endphp

<div class="cal-page">
    <x-ui.page-header :title="__('calendar.title')" :subtitle="$subtitle">
        <div class="cal-controls">
            <button type="button" class="ui-btn ui-btn-icon" wire:click="previous" aria-label="{{ __('calendar.previous.'.$viewName) }}"><x-heroicon-m-chevron-left class="cal-icon" aria-hidden="true" /></button>
            <x-ui.button wire:click="goToday">{{ __('calendar.today') }}</x-ui.button>
            <button type="button" class="ui-btn ui-btn-icon" wire:click="next" aria-label="{{ __('calendar.next.'.$viewName) }}"><x-heroicon-m-chevron-right class="cal-icon" aria-hidden="true" /></button>
            <x-ui.segmented wire:model.live="view" :options="$views" aria-label="{{ __('calendar.view_label') }}" />
            @if ($canManage)
                {{-- The calendar's one filled button, kept on purpose next to the views (docs/DECISIONS.md U-38). --}}
                <x-ui.button variant="primary" x-on:click="{!! Js::openWindow('edit-calendar-event', ['date' => $anchor->toDateString()]) !!}">{{ __('calendar.add') }}</x-ui.button>
            @endif
        </div>
    </x-ui.page-header>

    <div class="cal-filters" role="group" aria-label="{{ __('calendar.filters') }}">
        @foreach ($chips as $chip)
            <button type="button" wire:key="chip-{{ $chip['key'] }}" wire:click="toggleChip('{{ $chip['key'] }}')" @class(['cal-filter', 'is-off' => ! $chip['on']]) aria-pressed="{{ $chip['on'] ? 'true' : 'false' }}">
                <span class="cal-dot cal-c-{{ $chip['color'] }}" aria-hidden="true"></span>{{ $chip['label'] }}
            </button>
        @endforeach
        <button type="button" wire:click="toggleMine" @class(['cal-filter', 'is-off' => ! $mine]) aria-pressed="{{ $mine ? 'true' : 'false' }}">{{ __('calendar.only_mine') }}</button>
    </div>

    @if ($viewName === Index::VIEW_WEEK)
        <div class="ui-pane cal-board cal-wide">
            <div class="cal-x">@include('livewire.pages.calendar.hours')</div>
        </div>
        <div class="cal-narrow">@include('livewire.pages.calendar.list')</div>
    @elseif ($viewName === Index::VIEW_DAY)
        <div class="cal-day">
            <div class="ui-pane cal-board">@include('livewire.pages.calendar.hours')</div>
            @include('livewire.pages.calendar.aside')
        </div>
    @elseif ($viewName === Index::VIEW_MONTH)
        <div class="ui-pane cal-board"><div class="cal-x">@include('livewire.pages.calendar.month')</div></div>
    @else
        @include('livewire.pages.calendar.list')
    @endif
</div>
