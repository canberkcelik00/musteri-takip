<header class="fi-header fi-customer-header">
    <div class="fi-customer-header-title">
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        <h1 class="fi-header-heading">{{ $title }}</h1>

        @if (filled($subheading))
            <p class="fi-header-subheading">{{ $subheading }}</p>
        @endif
    </div>

    <dl class="fi-customer-header-amounts">
        @foreach ([['label' => __('customers.open_debt'), 'totals' => $openDebt], ['label' => __('customers.credit_balance'), 'totals' => $creditBalance]] as $amount)
            <div class="fi-customer-header-amount">
                <dt class="fi-customer-header-amount-label">{{ $amount['label'] }}</dt>
                @forelse ($amount['totals'] as $money)
                    <dd class="fi-customer-header-amount-value">{{ $money->format() }}</dd>
                @empty
                    <dd class="fi-customer-header-amount-value">{{ __('money.empty') }}</dd>
                @endforelse
            </div>
        @endforeach
    </dl>

    @if ($actions)
        <div class="fi-header-actions-ctn">
            <x-filament::actions :actions="$actions" />
        </div>
    @endif
</header>
