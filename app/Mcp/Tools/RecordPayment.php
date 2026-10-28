<?php

namespace App\Mcp\Tools;

use App\Actions\Payments\RecordPayment as RecordPaymentAction;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Exceptions\InvalidPaymentAllocation;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * The payment window for Claude, through the same RecordPayment with the token owner as the recorder. Only the
 * automatic allocation is offered (oldest open charge in the currency first, the rest as credit balance); there is
 * no manual allocation input.
 */
#[Description('Record a payment from a customer (id from search-customers). The amount is in minor units (kuruş/cents) of the given currency; it closes the customer\'s open charges in that currency, oldest due date first, and what is left becomes credit balance.')]
#[IsDestructive(false)]
class RecordPayment extends WriteTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $methods = PaymentMethod::query()->notArchived()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method): string => $method->id.' = '.$method->name)->implode(', ');

        return [
            'customer_id' => $schema->integer()->description('The paying customer; an archived customer cannot pay.')->required(),
            'amount_minor' => $schema->integer()->description('The amount in minor units, e.g. 150000 for 1.500 TL.')->required(),
            'currency' => $schema->string()->enum(array_column(Currency::cases(), 'value'))->required(),
            'payment_method_id' => $schema->integer()->description('Payment methods: '.$methods.'.')->required(),
            'paid_on' => $schema->string()->description('The payment date as Y-m-d; defaults to today.'),
            'note' => $schema->string()->description('A note on the payment, at most 1000 characters.'),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::MoneyManage;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('archived_at')],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->whereNull('archived_at')],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::query()->findOrFail($input['customer_id']);
        $currency = Currency::from($input['currency']);
        $note = trim($input['note'] ?? '');

        try {
            $payment = app(RecordPaymentAction::class)->handle($user, $customer, [
                'amount' => (int) $input['amount_minor'],
                'currency' => $currency,
                'paid_on' => $input['paid_on'] ?? today()->toDateString(),
                'payment_method_id' => (int) $input['payment_method_id'],
                'note' => $note === '' ? null : $note,
            ]);
        } catch (InvalidPaymentAllocation $exception) {
            return Response::error($exception->getMessage());
        }

        $payment->load(['allocations.charge', 'paymentMethod']);
        $allocated = (int) $payment->allocations->sum('amount');

        return Response::structured([
            'id' => $payment->id,
            'customer_id' => $customer->id,
            'customer' => $customer->short_name,
            'paid_on' => $payment->paid_on->toDateString(),
            'payment_method' => $payment->paymentMethod->name,
            'amount' => self::money(new Money($payment->amount, $currency)),
            'allocated' => self::money(new Money($allocated, $currency)),
            'added_to_credit_balance' => self::money(new Money($payment->amount - $allocated, $currency)),
            'allocations' => $payment->allocations->map(fn (PaymentAllocation $allocation): array => [
                'charge_id' => $allocation->charge_id,
                'description' => $allocation->charge->description,
                'due_date' => $allocation->charge->due_date->toDateString(),
                'amount' => self::money(new Money($allocation->amount, $currency)),
                'charge_status' => $allocation->charge->status->value,
            ])->all(),
        ]);
    }
}
