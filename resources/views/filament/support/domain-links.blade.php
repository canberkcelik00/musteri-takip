{{--
    A service's domains in the customer card (G-4C.6, docs/UI.md §2 Kural 5 exception): each domain with two
    icon-only controls, "open the site" (new tab) and "copy". Their names are the tooltip and the aria-label.
    This sits in the name column, which Filament wraps in a <button> (T-73), so the controls are spans with a
    role, never a nested <button> or <a>; they stop the click, so they do not open the row's edit window. A
    domain that is not a plain host name gets no "open" control, so a typed value can never become an odd address.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

@foreach ($domains as $domain)
    <span class="fi-domain" x-data="{ copied: false }">
        <span class="fi-domain-name">{{ $domain }}</span>
        @if (preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $domain) === 1)
            <span
                role="link"
                tabindex="0"
                class="fi-domain-icon"
                title="{{ __('customer_services.open_site') }}"
                aria-label="{{ __('customer_services.open_site') }}"
                x-on:click.stop="window.open(@js('https://'.$domain), '_blank', 'noopener,noreferrer')"
                x-on:keydown.enter.stop.prevent="window.open(@js('https://'.$domain), '_blank', 'noopener,noreferrer')"
            >
                <x-filament::icon :icon="Heroicon::OutlinedArrowTopRightOnSquare" />
            </span>
        @endif
        <span
            role="button"
            tabindex="0"
            class="fi-domain-icon"
            title="{{ __('customer_services.copy_domain') }}"
            aria-label="{{ __('customer_services.copy_domain') }}"
            x-on:click.stop="navigator.clipboard.writeText(@js($domain)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
            x-on:keydown.enter.stop.prevent="navigator.clipboard.writeText(@js($domain)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
        >
            <x-filament::icon :icon="Heroicon::OutlinedClipboardDocument" x-show="! copied" />
            <x-filament::icon :icon="Heroicon::OutlinedCheck" x-show="copied" x-cloak />
        </span>
    </span>@if (! $loop->last), @endif
@endforeach
