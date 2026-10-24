@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="__('charges.edit_heading')" submit="save">
    <div class="ui-form">
        <div class="is-full">
            <x-ui.money-input
                :label="__('charges.amount')"
                wire:model="amount"
                :suffix="$charge->currency->getLabel()"
                :hint="$locked ? __('charges.has_allocations') : null"
                :disabled="$locked"
            />
        </div>
        <div class="is-full"><x-ui.date-input :label="__('charges.due_date')" wire:model="due_date" /></div>

        @if ($canSplit || $canRemind || $canCancel)
            <div class="ui-actions-section is-full">
                <h3>{{ __('charges.actions_section') }}</h3>
                @if ($canSplit || $canRemind)
                    <div class="ui-actions-row">
                        @if ($canSplit)
                            <x-ui.button x-on:click="{!! Js::openWindow('split-charge', ['charge' => $charge->id]) !!}">{{ __('charges.split') }}</x-ui.button>
                        @endif
                        @if ($canRemind)
                            <x-ui.button x-on:click="{!! Js::openWindow('send-reminder', ['charge' => $charge->id]) !!}">{{ __('reminders.send') }}</x-ui.button>
                        @endif
                    </div>
                @endif
                @if ($canCancel)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('cancel-charge', ['charge' => $charge->id]) !!}">{{ __('charges.cancel') }}</x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-ui.window>
