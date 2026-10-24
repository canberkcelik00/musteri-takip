@props(['domains'])

{{--
    A service's domains, each with two icon-only controls, "open the site" (new tab) and "copy" (docs/UI.md §2 Kural 5
    exception). Their names are the tooltip and the aria-label; a click on them does not open the row. A domain that is
    not a plain host name gets no "open" control, so a typed value can never become an odd address.
--}}
@foreach ($domains as $domain)
    <span class="ui-domain" x-data="{ copied: false }">
        <span class="ui-domain-name">{{ $domain }}</span>
        @if (preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $domain) === 1)
            <a
                href="https://{{ $domain }}"
                target="_blank"
                rel="noopener noreferrer"
                class="ui-icon-link"
                title="{{ __('customer_services.open_site') }}"
                aria-label="{{ __('customer_services.open_site') }}"
                x-on:click.stop
            ><x-heroicon-o-arrow-top-right-on-square aria-hidden="true" /></a>
        @endif
        <button
            type="button"
            class="ui-icon-link"
            title="{{ __('customer_services.copy_domain') }}"
            aria-label="{{ __('customer_services.copy_domain') }}"
            x-on:click.stop="navigator.clipboard.writeText(@js($domain)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
        >
            <x-heroicon-o-clipboard-document x-show="! copied" aria-hidden="true" />
            <x-heroicon-o-check x-show="copied" x-cloak aria-hidden="true" />
        </button>
    </span>@if (! $loop->last), @endif
@endforeach
