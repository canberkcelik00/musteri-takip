@props(['columns', 'sort' => '', 'direction' => 'asc', 'empty' => false, 'end' => false, 'card' => true, 'count' => null])

{{--
    A list's card (docs/UI.md §5): toolbar slot, a real <table> with sortable column heads, the rows as the default slot
    (<x-ui.row>), an optional <tfoot> (`foot`), the empty state (`emptyState`, shown instead of the table) and the pager.
    A column is ['key', 'label', 'sortable', 'from' (md|lg|xl), 'end' (right-aligned)]; below its breakpoint the column
    is hidden and the cells of the rows must carry the same `from` class. `:card="false"` draws it without its own card,
    for a table that sits inside a card that already has the surface. `count` is the filtered record count, drawn at the
    right end of the toolbar.
--}}
<div {{ $attributes->class(['ui-card' => $card, 'ui-pane' => $card, 'is-flush' => $card]) }}>
    @isset($toolbar)
        <div class="ui-toolbar">
            {{ $toolbar }}
            @if ($count !== null)
                <span class="ui-toolbar-count">{{ $count }}</span>
            @endif
        </div>
    @endisset

    @if ($empty && isset($emptyState))
        <div class="ui-empty">{{ $emptyState }}</div>
    @else
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            @php
                                $active = ($column['sortable'] ?? false) && $sort === $column['key'];
                            @endphp
                            <th
                                scope="col"
                                @class([
                                    'ui-from-'.($column['from'] ?? '') => isset($column['from']),
                                    'is-end' => $column['end'] ?? false,
                                ])
                                @if ($active) aria-sort="{{ $direction === 'desc' ? 'descending' : 'ascending' }}" @endif
                            >
                                @if ($column['sortable'] ?? false)
                                    <button type="button" class="ui-sort" wire:click="sortBy('{{ $column['key'] }}')">
                                        {{ $column['label'] }}
                                        @if ($active && $direction === 'desc')
                                            <x-heroicon-m-arrow-down aria-hidden="true" />
                                        @elseif ($active)
                                            <x-heroicon-m-arrow-up aria-hidden="true" />
                                        @endif
                                    </button>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                        @if ($end)
                            <th scope="col"><span class="sr-only">{{ __('ui.table.actions') }}</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>{{ $slot }}</tbody>
                @isset($foot)
                    <tfoot>{{ $foot }}</tfoot>
                @endisset
            </table>
        </div>
    @endif

    @isset($pager)
        {{ $pager }}
    @endisset
</div>
