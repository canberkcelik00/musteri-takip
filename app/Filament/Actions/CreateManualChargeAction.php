<?php

namespace App\Filament\Actions;

use App\Actions\Charges\CreateManualCharge;
use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * The form for a charge no rule produces: customer, description, amount, currency, due date and,
 * optionally, one of the customer's services. It only calls CreateManualCharge. On the customer
 * card the customer is fixed with customer().
 */
class CreateManualChargeAction extends Action
{
    public const string CHARGES_CHANGED_EVENT = 'charges-changed';

    protected ?Customer $customer = null;

    public static function getDefaultName(): ?string
    {
        return 'createManualCharge';
    }

    /**
     * Fix the customer (the customer card); the customer field is then left out of the form.
     */
    public function customer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('charges.create'))
            ->icon(Heroicon::Plus)
            ->modalHeading(__('charges.create_heading'))
            ->modalSubmitActionLabel(__('charges.create'))
            ->slideOver()
            ->fillForm(fn (): array => [
                'customer_id' => $this->customer?->getKey(),
                'currency' => Currency::Try->value,
                'due_date' => today()->toDateString(),
            ])
            ->schema([
                Text::make(__('charges.create_helper'))->columnSpanFull(),
                Select::make('customer_id')
                    ->label(__('charges.customer'))
                    ->options(fn (): array => Customer::query()->notArchived()->orderBy('short_name')->pluck('short_name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->customer === null)
                    ->afterStateUpdated(fn (Set $set) => $set('customer_service_id', null))
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->label(__('charges.description'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                MoneyInput::make('amount')
                    ->label(__('charges.amount'))
                    ->required()
                    ->currencyField(),
                CurrencySelect::make('currency'),
                DatePicker::make('due_date')
                    ->label(__('charges.due_date'))
                    ->required(),
                Select::make('customer_service_id')
                    ->label(__('charges.customer_service'))
                    ->options(fn (Get $get): array => $this->serviceOptions($get))
                    ->searchable()
                    ->placeholder(__('money.empty')),
            ])
            ->action(function (array $data, Action $action, CreateManualCharge $createManualCharge, Component $livewire): void {
                $customer = $this->customer ?? Customer::query()->findOrFail($data['customer_id']);

                try {
                    $createManualCharge->handle($customer, [
                        'description' => $data['description'],
                        'amount' => $data['amount'],
                        'currency' => $data['currency'] instanceof Currency ? $data['currency'] : Currency::from($data['currency']),
                        'due_date' => $data['due_date'],
                        'customer_service_id' => filled($data['customer_service_id'] ?? null) ? (int) $data['customer_service_id'] : null,
                    ]);
                } catch (InvalidCharge $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                $action->successNotificationTitle(__('charges.created'))->success();

                $livewire->dispatch(self::CHARGES_CHANGED_EVENT);
            });
    }

    /**
     * @return array<int, string>
     */
    private function serviceOptions(Get $get): array
    {
        $customerId = $this->customer?->getKey() ?? $get('customer_id');

        if (blank($customerId)) {
            return [];
        }

        return Customer::query()->find($customerId)
            ?->customerServices()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all() ?? [];
    }
}
