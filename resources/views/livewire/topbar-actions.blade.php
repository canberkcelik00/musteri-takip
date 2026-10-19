<div class="fi-topbar-actions" data-topbar-actions>
    {{ $this->recordPaymentAction() }}

    <span class="fi-topbar-new-action">
        {{ $this->createCustomerAction() }}
    </span>

    <x-filament-actions::modals />
</div>
