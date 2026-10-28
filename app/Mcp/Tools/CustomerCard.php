<?php

namespace App\Mcp\Tools;

use App\Actions\Payments\SumOpenCharges;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The customer page for Claude: contacts and services for everyone; prices, open debt, credit balance and open
 * charges only with money.view, as on the page. Customer notes, tax details and service secrets (a license key, a
 * server) are not part of the answer.
 */
#[Description('One customer by id (from search-customers): contacts, services with their domains and end dates and, when the user may see money, prices, open debt and credit balance per currency and the open charges.')]
#[IsReadOnly]
#[IsIdempotent]
class CustomerCard extends BaseTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('The customer id.')->required(),
        ];
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()
            ->with([
                'contacts' => fn ($contacts) => $contacts->orderByDesc('is_primary')->orderBy('name'),
                'customerServices' => fn ($services) => $services->with('items')->orderBy('end_date')->orderBy('id'),
            ])
            ->findOrFail($input['customer_id']);

        $money = $user->can(Permission::MoneyView->value);

        $card = [
            'id' => $customer->id,
            'short_name' => $customer->short_name,
            'legal_title' => $customer->legal_title,
            'archived' => $customer->isArchived(),
            'contacts' => $customer->contacts->map(fn (Contact $contact): array => [
                'name' => $contact->name,
                'title' => $contact->title,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'primary' => $contact->is_primary,
            ])->all(),
            'services' => $customer->customerServices->map(fn (CustomerService $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'status' => $service->status->value,
                'start_date' => $service->start_date->toDateString(),
                'end_date' => $service->end_date->toDateString(),
                'period_months' => $service->period_months,
                'free' => $service->is_free,
                'domains' => $service->domains(),
                ...($money ? ['price' => self::money(new Money($service->price, $service->currency))] : []),
            ])->all(),
        ];

        if ($money) {
            $charges = $customer->charges()
                ->where('status', ChargeStatus::Open)
                ->withSum('allocations', 'amount')
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(self::MAX_ROWS)
                ->get()
                ->each(fn (Charge $charge) => $charge->setRelation('customer', $customer));

            $card['open_debt'] = self::totals($this->openDebt($customer));
            $card['credit_balance'] = self::totals($customer->creditBalances());
            $card['open_charges'] = $charges->map(fn (Charge $charge): array => self::chargeRow($charge))->all();
        }

        return Response::structured($card);
    }

    /**
     * @return array<string, Money>
     */
    private function openDebt(Customer $customer): array
    {
        $sum = app(SumOpenCharges::class);
        $totals = [];

        foreach (Currency::cases() as $currency) {
            $amount = $sum->handle($customer, $currency);

            if ($amount > 0) {
                $totals[$currency->value] = new Money($amount, $currency);
            }
        }

        return $totals;
    }
}
