@props(['domains', 'cloudflare' => [], 'check' => false])

{{--
    A service's domains, one per row, each with two icon-only controls, "open the site" (new tab) and "copy" (docs/UI.md
    §2 Kural 5 exception). Their names are the tooltip and the aria-label; a click on them does not open the row. A domain
    that is not a plain host name gets no "open" control, so a typed value can never become an odd address.
    A domain in `cloudflare` gets the Cloudflare pill after its name: a status sign, not a control (U-28, U-31).
--}}
@foreach ($domains as $domain)
    <div class="ui-domain" x-data="{ copied: false }">
        <span class="ui-domain-name">{{ $domain }}</span>
        @if (in_array($domain, $cloudflare, true))
            <x-ui.tooltip :label="__('customer_services.on_cloudflare')"><span
                class="ui-domain-badge"
                role="img"
                aria-label="{{ __('customer_services.on_cloudflare') }}"
            ><x-ui.cloudflare-icon />{{ __('customer_services.cloudflare') }}</span></x-ui.tooltip>
        @endif
        @if (preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $domain) === 1)
            <x-ui.tooltip :label="__('customer_services.open_site')"><a
                href="https://{{ $domain }}"
                target="_blank"
                rel="noopener noreferrer"
                class="ui-icon-link"
                aria-label="{{ __('customer_services.open_site') }}"
                x-on:click.stop
            ><x-heroicon-o-arrow-top-right-on-square aria-hidden="true" /></a></x-ui.tooltip>
        @endif
        <x-ui.tooltip :label="__('customer_services.copy_domain')"><button
            type="button"
            class="ui-icon-link"
            aria-label="{{ __('customer_services.copy_domain') }}"
            x-on:click.stop="navigator.clipboard.writeText(@js($domain)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
        >
            <x-heroicon-o-clipboard-document x-show="! copied" aria-hidden="true" />
            <x-heroicon-o-check x-show="copied" x-cloak aria-hidden="true" />
        </button></x-ui.tooltip>
        @if ($check && ($host = \App\Support\Dns\HostName::normalize($domain)) !== null)
            <x-ui.button size="sm" x-on:click.stop="{!! \App\Support\Ui\Js::openWindow('check-domain', ['domain' => $host]) !!}">{{ __('domain_check.check') }}</x-ui.button>
        @endif
    </div>
@endforeach
