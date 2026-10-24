@if ($paginator->hasPages())
    <nav class="ui-pager" role="navigation" aria-label="{{ __('ui.table.pagination') }}">
        <button type="button" class="ui-pager-item" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage())>{{ __('ui.table.previous') }}</button>

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="ui-pager-item" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span class="ui-pager-item" aria-current="page">{{ $page }}</span>
                    @else
                        <button type="button" class="ui-pager-item" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        <button type="button" class="ui-pager-item" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages())>{{ __('ui.table.next') }}</button>
    </nav>
@endif
