{{--
    The company logo as a horizontal lockup: the cube (orange half fixed, the other half in the text color) and the
    "idea" wordmark, a mask filled with the text color, so one set of files serves both themes.
--}}
<div {{ $attributes->class(['ui-brand']) }} role="img" aria-label="{{ __('ui.brand_name') }}">
    <span class="ui-brand-cube" aria-hidden="true">
        <svg viewBox="0 0 58 66">
            <defs>
                <clipPath id="ui-brand-cube-left"><rect width="29" height="66"/></clipPath>
                <clipPath id="ui-brand-cube-right"><rect x="29" width="29" height="66"/></clipPath>
            </defs>
            <g fill="none" stroke-width="6.5">
                <path stroke="#fd4f00" d="M54.5 17.5v31"/>
                <path stroke="#fd4f00" clip-path="url(#ui-brand-cube-left)" d="M54 17.5 29 3.5 4 17.5v31L29 62.5 54 48.5M4 17.5 29 33 4 48.5"/>
                <path stroke="currentColor" clip-path="url(#ui-brand-cube-right)" d="M4 17.5 29 3.5 54 17.5 29 33 54 48.5 29 62.5 4 48.5"/>
            </g>
        </svg>
    </span>
    <span class="ui-brand-word" aria-hidden="true"></span>
</div>
