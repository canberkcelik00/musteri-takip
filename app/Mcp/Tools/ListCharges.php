<?php

namespace App\Mcp\Tools;

use App\Actions\Charges\FilterCharges;
use App\Actions\Charges\SumRemainingByCurrency;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Charge;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The "Borçlar" list for Claude, through the page's own FilterCharges; the remaining total per currency follows the
 * filter as the page's total row does. Archived customers are left out unless asked for.
 */
#[Description('List charges (debts), the earliest due date first: open ones by default, or paid, cancelled or all; only overdue ones; a due date range; one customer; one currency. Returns the remaining open amount per currency of everything the filter matched (never added across currencies). Archived customers are left out unless include_archived is true.')]
#[IsReadOnly]
#[IsIdempotent]
class ListCharges extends BaseTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['open', 'paid', 'cancelled', 'all'])->description('Defaults to open.'),
            'only_overdue' => $schema->boolean()->description('Only open charges past their due date.'),
            'due_from' => $schema->string()->format('date')->description('First due date, YYYY-MM-DD.'),
            'due_to' => $schema->string()->format('date')->description('Last due date, YYYY-MM-DD.'),
            'customer_id' => $schema->integer()->description('Only this customer\'s charges.'),
            'currency' => $schema->string()->enum(array_column(Currency::cases(), 'value')),
            'include_archived' => $schema->boolean()->description('Also list charges of archived customers.'),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::MoneyView;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'status' => [Rule::in(['open', 'paid', 'cancelled', 'all'])],
            'only_overdue' => ['boolean'],
            'due_from' => ['date_format:Y-m-d'],
            'due_to' => ['date_format:Y-m-d', 'after_or_equal:due_from'],
            'customer_id' => ['integer'],
            'currency' => [Rule::enum(Currency::class)],
            'include_archived' => ['boolean'],
        ]);

        $filtered = fn () => app(FilterCharges::class)->handle(
            status: ChargeStatus::tryFrom($input['status'] ?? 'open'),
            onlyOverdue: (bool) ($input['only_overdue'] ?? false),
            hideArchived: ! ($input['include_archived'] ?? false),
            currency: isset($input['currency']) ? Currency::from($input['currency']) : null,
            customerId: isset($input['customer_id']) ? (int) $input['customer_id'] : null,
            dueFrom: isset($input['due_from']) ? Carbon::parse($input['due_from']) : null,
            dueTo: isset($input['due_to']) ? Carbon::parse($input['due_to']) : null,
        );

        $charges = $filtered()
            ->with('customer')
            ->withSum('allocations', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get();

        return Response::structured([
            'total' => $filtered()->count(),
            'remaining_open_totals' => self::totals(app(SumRemainingByCurrency::class)->handle($filtered()->where('status', ChargeStatus::Open))),
            'charges' => $charges->map(fn (Charge $charge): array => self::chargeRow($charge))->all(),
        ]);
    }
}
