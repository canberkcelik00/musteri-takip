<?php

namespace App\Mcp\Tools;

use App\Enums\McpAccess;
use App\Enums\Permission;
use App\Models\Charge;
use App\Models\McpConnection;
use App\Models\Task;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Tool;
use Laravel\Passport\Passport;

/**
 * The common gate of every Claude tool (U-42, U-43): the OAuth token must carry the MCP scope, the user must have
 * given the connection write access for a write tool, and the user's role must hold the tool's permission, or the
 * tool is neither listed nor run. Every run is written to the `mcp` log channel (who, which tool, which connection).
 * An answer is built from hand-picked fields only, never from a model's toArray(), so a secret (a
 * server key, a password, a license key, a token) can never leak into it.
 */
abstract class BaseTool extends Tool
{
    /**
     * The most rows a list returns; `total` tells how many the filter matched.
     */
    public const int MAX_ROWS = 50;

    public function shouldRegister(Request $request): bool
    {
        return $this->allowedUser($request) !== null;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->allowedUser($request);

        if ($user === null) {
            throw new AuthorizationException('This tool is not available to this token.');
        }

        Log::channel('mcp')->info('Tool call', [
            'user_id' => $user->id,
            'user' => $user->email,
            'tool' => $this->name(),
            'client_id' => $user->currentMcpClientId(),
            'client' => Passport::client()->newQuery()->find($user->currentMcpClientId())?->name,
            ...$this->loggedArguments($request),
        ]);

        return $this->run($request, $user);
    }

    abstract protected function run(Request $request, User $user): Response|ResponseFactory;

    /**
     * The role permission the tool needs; null when every user may use it.
     */
    protected function permission(): ?Permission
    {
        return null;
    }

    /**
     * Whether the tool changes data, so the user must have given the connection write access.
     */
    protected function requiresWrite(): bool
    {
        return false;
    }

    /**
     * What of the request goes into the log line: nothing for a read tool; a write tool logs its input.
     *
     * @return array<string, mixed>
     */
    protected function loggedArguments(Request $request): array
    {
        return [];
    }

    private function allowedUser(Request $request): ?User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan(Registrar::OAUTH_SCOPE)) {
            return null;
        }

        if ($this->requiresWrite() && McpConnection::accessFor($user, (string) $user->currentMcpClientId()) !== McpAccess::ReadWrite) {
            return null;
        }

        $permission = $this->permission();

        return $permission === null || $user->can($permission->value) ? $user : null;
    }

    /**
     * @return array{currency: string, amount_minor: int, formatted: string}
     */
    protected static function money(Money $money): array
    {
        return [
            'currency' => $money->currency->value,
            'amount_minor' => $money->amount,
            'formatted' => $money->format(),
        ];
    }

    /**
     * One entry per currency, never added across currencies.
     *
     * @param  array<string, Money>  $totals
     * @return list<array{currency: string, amount_minor: int, formatted: string}>
     */
    protected static function totals(array $totals): array
    {
        return array_values(array_map(fn (Money $money): array => self::money($money), $totals));
    }

    /**
     * A charge row; the charge is loaded with its customer and `withSum('allocations', 'amount')`.
     *
     * @return array<string, mixed>
     */
    protected static function chargeRow(Charge $charge): array
    {
        return [
            'id' => $charge->id,
            'customer_id' => $charge->customer_id,
            'customer' => $charge->customer->short_name,
            'description' => $charge->description,
            'status' => $charge->status->value,
            'due_date' => $charge->due_date->toDateString(),
            'amount' => self::money(new Money($charge->amount, $charge->currency)),
            'remaining' => self::money(new Money(max(0, $charge->remainingAmount()), $charge->currency)),
            'days_overdue' => $charge->daysOverdue(),
        ];
    }

    /**
     * A task row; the task is loaded with its customer and assignee.
     *
     * @return array<string, mixed>
     */
    protected static function taskRow(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'notes' => $task->notes,
            'customer_id' => $task->customer_id,
            'customer' => $task->customer?->short_name,
            'assignee' => $task->assignee?->name,
            'due_date' => $task->due_date?->toDateString(),
            'overdue' => $task->isOverdue(),
            'completed_at' => $task->completed_at?->toDateTimeString(),
        ];
    }
}
