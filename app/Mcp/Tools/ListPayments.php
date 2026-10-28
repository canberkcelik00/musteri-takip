<?php

namespace App\Mcp\Tools;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The "Ödemeler" list for Claude, newest first as on the page, with a payment date range the page does not have; the
 * total is per currency.
 */
#[Description('List received payments, the newest first: a payment date range and one customer. Returns the total per currency of everything the filter matched (never added across currencies).')]
#[IsReadOnly]
#[IsIdempotent]
class ListPayments extends BaseTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->format('date')->description('First payment date, YYYY-MM-DD.'),
            'to' => $schema->string()->format('date')->description('Last payment date, YYYY-MM-DD.'),
            'customer_id' => $schema->integer()->description('Only this customer\'s payments.'),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::MoneyView;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d', 'after_or_equal:from'],
            'customer_id' => ['integer'],
        ]);

        $filtered = fn (): Builder => Payment::query()
            ->when(isset($input['from']), fn (Builder $query) => $query->whereDate('paid_on', '>=', $input['from']))
            ->when(isset($input['to']), fn (Builder $query) => $query->whereDate('paid_on', '<=', $input['to']))
            ->when(isset($input['customer_id']), fn (Builder $query) => $query->where('customer_id', (int) $input['customer_id']));

        $payments = $filtered()
            ->with(['customer', 'paymentMethod'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get();

        $totals = $filtered()
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as total')
            ->toBase()
            ->get()
            ->map(fn (object $row): Money => new Money((int) $row->total, Currency::from($row->currency)));

        return Response::structured([
            'total' => $filtered()->count(),
            'totals' => self::totals(Money::sumByCurrency($totals)),
            'payments' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'customer' => $payment->customer->short_name,
                'paid_on' => $payment->paid_on->toDateString(),
                'amount' => self::money(new Money($payment->amount, $payment->currency)),
                'method' => $payment->paymentMethod?->name,
                'note' => $payment->note,
            ])->all(),
        ]);
    }
}
