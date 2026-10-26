@props(['title', 'subtitle' => null])

{{--
    The page title (h1) with an optional subtitle; the slot holds the page's actions (its one primary button), placed
    at the end of the row.
--}}
<div {{ $attributes->class(['ui-page-head']) }}>
    <div>
        <h1 class="ui-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="ui-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
