@php
    use App\Livewire\Windows\PullFromServer;

    $closed = $done || $error !== null;
@endphp

<x-ui.window :title="__('servers.pull_heading', ['name' => $server->name])" :submit="$closed ? null : 'importAccounts'" :submit-label="__('servers.pull_confirm')">
    @if ($error !== null)
        <div class="ui-error-box" role="alert">{{ $error }}</div>
    @elseif ($done)
        <p class="ui-note is-full">{{ implode(', ', $resultParts) }}</p>

        @if ($resultProblems !== [])
            <div class="ui-error-box" role="alert">
                @foreach ($resultProblems as $problem)
                    <div>{{ $problem }}</div>
                @endforeach
            </div>
        @endif
    @else
        <p class="ui-note">{{ $summary }}</p>

        <div class="ui-form">
            @if ($packageNames !== [])
                <div class="is-full">
                    <h3 class="ui-form-section">{{ __('servers.pull_new_packages') }}</h3>
                    <p class="ui-hint">{{ __('servers.pull_new_packages_helper') }}</p>
                </div>
                @foreach ($packageNames as $position => $name)
                    <div class="ui-field is-full" wire:key="pull-package-{{ $position }}">
                        <span class="ui-label">{{ __('servers.pull_package') }}: {{ $name }}</span>
                        <div class="ui-form">
                            <x-ui.money-input :label="__('servers.pull_package_price')" wire:model="prices.{{ $position }}" />
                            <x-ui.select :label="__('money.currency')" wire:model="currencies.{{ $position }}" :options="$currencyOptions" />
                        </div>
                    </div>
                @endforeach
            @endif

            @if ($rows !== [])
                <div class="is-full">
                    <h3 class="ui-form-section">{{ __('servers.pull_accounts') }}</h3>
                    <p class="ui-hint">{{ __('servers.pull_accounts_helper') }}</p>
                </div>
                @foreach ($rows as $position => $row)
                    @continue(in_array($position, $removed, true))
                    <div class="ui-field is-full" wire:key="pull-account-{{ $position }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="ui-label">{{ __('servers.pull_account') }}: {{ $row['account'] }}</span>
                            <x-ui.button size="sm" wire:click="removeRow({{ $position }})"><x-heroicon-o-trash aria-hidden="true" />{{ __('actions.take_out') }}</x-ui.button>
                        </div>
                        <x-ui.select
                            :label="__('servers.pull_customer_name')"
                            wire:model.live="choices.{{ $position }}"
                            :options="array_combine($row['domains'], $row['domains']) + [PullFromServer::OTHER => __('servers.pull_customer_name_other')]"
                        />
                        @if (($choices[$position] ?? null) === PullFromServer::OTHER)
                            <x-ui.input :label="__('servers.pull_customer_name_custom')" wire:model="customs.{{ $position }}" maxlength="255" />
                        @endif
                    </div>
                @endforeach
            @endif

            @if ($isCpanel)
                <div class="is-full"><x-ui.date-input :label="__('servers.pull_start_date')" wire:model="start_date" /><span class="ui-hint">{{ __('servers.pull_start_date_helper') }}</span></div>
            @endif
        </div>
    @endif

    @if ($closed)
        <x-slot:footer>
            <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
        </x-slot:footer>
    @endif
</x-ui.window>
