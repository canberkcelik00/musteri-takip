<?php

use App\Actions\Account\CreateMcpToken;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\McpAccess;
use App\Enums\Permission;
use App\Mail\TaskAssignedMail;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\CreateTask;
use App\Mcp\Tools\RecordPayment;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo('2026-10-28 10:00');
});

it('shows and runs the write tools only with a read and write token', function () {
    $admin = User::factory()->admin()->create();
    $task = Task::factory()->create(['assigned_to' => $admin->id]);

    mcpAs($admin)->tools()->assertNotRegistered([CreateTask::class, CompleteTask::class, RecordPayment::class]);
    mcpAs($admin)->tool(CreateTask::class, ['title' => 'Yedek al'])->assertHasErrors();
    mcpAs($admin)->tool(CompleteTask::class, ['task_id' => $task->id])->assertHasErrors();

    expect(Task::query()->count())->toBe(1)
        ->and($task->refresh()->completed_at)->toBeNull();

    mcpAs($admin, McpAccess::ReadWrite)->tools()->assertRegistered([CreateTask::class, CompleteTask::class, RecordPayment::class]);
});

it('offers no delete, cancel, archive or undo tool', function () {
    $token = app(CreateMcpToken::class)->handle(User::factory()->admin()->create(), 'Ofis', McpAccess::ReadWrite);

    $names = collect($this->withToken($token)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []])
        ->assertOk()
        ->json('result.tools'))->pluck('name');

    expect($names)->toHaveCount(10)
        ->toContain('create-task', 'complete-task', 'record-payment')
        ->and($names->filter(fn (string $name): bool => preg_match('/delete|cancel|archive|undo|remove|reopen/', $name) === 1))->toBeEmpty();
});

it('does not let a developer record a payment', function () {
    $customer = Customer::factory()->create();

    mcpAs(User::factory()->developer()->create(), McpAccess::ReadWrite)->tools()
        ->assertRegistered([CreateTask::class, CompleteTask::class])
        ->assertNotRegistered(RecordPayment::class);

    mcpAs(User::factory()->developer()->create(), McpAccess::ReadWrite)
        ->tool(RecordPayment::class, ['customer_id' => $customer->id, 'amount_minor' => 1000, 'currency' => 'TRY', 'payment_method_id' => PaymentMethod::factory()->create()->id])
        ->assertHasErrors();

    expect(Payment::query()->count())->toBe(0);
});

it('does not let a user without tasks.manage add or finish a task', function () {
    $user = User::factory()->developer()->create();
    Role::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);
    $user->refresh();
    $task = Task::factory()->create(['assigned_to' => $user->id]);

    mcpAs($user, McpAccess::ReadWrite)->tools()->assertNotRegistered([CreateTask::class, CompleteTask::class]);
    mcpAs($user, McpAccess::ReadWrite)->tool(CreateTask::class, ['title' => 'Yedek al'])->assertHasErrors();
    mcpAs($user, McpAccess::ReadWrite)->tool(CompleteTask::class, ['task_id' => $task->id])->assertHasErrors();

    expect(Task::query()->count())->toBe(1)
        ->and($task->refresh()->completed_at)->toBeNull();
});

it('writes a write call to the mcp log channel with its arguments', function () {
    $user = User::factory()->admin()->create(['email' => 'sahip@example.com']);

    Log::shouldReceive('channel')->once()->with('mcp')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Tool call'
        && $context === ['user_id' => $user->id, 'user' => 'sahip@example.com', 'tool' => 'create-task', 'token' => 'Ofis', 'arguments' => ['title' => 'Yedek al']]);

    mcpAs($user, McpAccess::ReadWrite)->tool(CreateTask::class, ['title' => 'Yedek al'])->assertOk();
});

it('adds a task for the token owner as the panel does', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create(['name' => 'Ayşe']);
    $customer = Customer::factory()->create(['short_name' => 'Acme']);

    mcpAs($admin, McpAccess::ReadWrite)
        ->tool(CreateTask::class, ['title' => '  Yedek al  ', 'notes' => 'Haftalık', 'customer_id' => $customer->id, 'due_date' => '2026-10-30'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('task.title', 'Yedek al')
            ->where('task.customer', 'Acme')
            ->where('task.assignee', 'Ayşe')
            ->where('task.due_date', '2026-10-30')
            ->where('warning', null)
            ->etc());

    $task = Task::query()->sole();

    expect($task)
        ->created_by->toBe($admin->id)
        ->assigned_to->toBe($admin->id)
        ->notes->toBe('Haftalık');

    Mail::assertNothingSent();
});

it('e-mails another assignee', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $developer = User::factory()->developer()->create(['email' => 'yazilimci@example.com']);

    mcpAs($admin, McpAccess::ReadWrite)
        ->tool(CreateTask::class, ['title' => 'Siteyi güncelle', 'assigned_to' => $developer->id])
        ->assertOk();

    expect(Task::query()->sole())
        ->created_by->toBe($admin->id)
        ->assigned_to->toBe($developer->id);

    Mail::assertSent(TaskAssignedMail::class, fn (TaskAssignedMail $mail): bool => $mail->hasTo('yazilimci@example.com'));
});

it('keeps the task and warns when the assignment e-mail fails', function () {
    $admin = User::factory()->admin()->create();
    $developer = User::factory()->developer()->create();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP kapalı'));

    mcpAs($admin, McpAccess::ReadWrite)
        ->tool(CreateTask::class, ['title' => 'Siteyi güncelle', 'assigned_to' => $developer->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('task', null)
            ->where('warning', __('tasks.mail_not_sent', ['message' => 'SMTP kapalı']))
            ->etc());

    expect(Task::query()->count())->toBe(1);
});

it('refuses a task for an archived customer or an inactive assignee', function () {
    $admin = User::factory()->admin()->create();

    mcpAs($admin, McpAccess::ReadWrite)
        ->tool(CreateTask::class, ['title' => 'Yedek al', 'customer_id' => Customer::factory()->archived()->create()->id])
        ->assertHasErrors([__('tasks.customer_archived')]);

    mcpAs($admin, McpAccess::ReadWrite)
        ->tool(CreateTask::class, ['title' => 'Yedek al', 'assigned_to' => User::factory()->deactivated()->create()->id])
        ->assertHasErrors([__('tasks.assignee_inactive')]);

    expect(Task::query()->count())->toBe(0);
});

it('refuses a task with invalid input', function (array $arguments) {
    mcpAs(User::factory()->admin()->create(), McpAccess::ReadWrite)->tool(CreateTask::class, $arguments)->assertHasErrors();

    expect(Task::query()->count())->toBe(0);
})->with([
    'no title' => [['notes' => 'Haftalık']],
    'long title' => [['title' => str_repeat('a', 256)]],
    'unknown customer' => [['title' => 'Yedek al', 'customer_id' => 999]],
    'unknown assignee' => [['title' => 'Yedek al', 'assigned_to' => 999]],
    'bad date' => [['title' => 'Yedek al', 'due_date' => '30.10.2026']],
]);

it('finishes a task by the token owner and leaves a finished one as it was', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $open = Task::factory()->create(['title' => 'Yedek al']);
    $done = Task::factory()->completed()->create(['completed_by' => $other->id, 'completed_at' => '2026-10-20 09:00']);

    mcpAs($admin, McpAccess::ReadWrite)->tool(CompleteTask::class, ['task_id' => $open->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('task.title', 'Yedek al')
            ->where('task.completed_at', '2026-10-28 10:00:00')
            ->etc());

    mcpAs($admin, McpAccess::ReadWrite)->tool(CompleteTask::class, ['task_id' => $done->id])->assertOk();

    expect($open->refresh()->completed_by)->toBe($admin->id)
        ->and($done->refresh()->completed_by)->toBe($other->id)
        ->and($done->completed_at->format('Y-m-d H:i'))->toBe('2026-10-20 09:00');

    mcpAs($admin, McpAccess::ReadWrite)->tool(CompleteTask::class, ['task_id' => 999])->assertHasErrors();
});

it('records a payment with the automatic allocation as the panel does', function () {
    $admin = User::factory()->admin()->create();
    $method = PaymentMethod::factory()->create(['name' => 'Havale']);
    $customer = Customer::factory()->create(['short_name' => 'Acme']);
    $old = Charge::factory()->for($customer)->create(['amount' => 100000, 'currency' => Currency::Try, 'due_date' => '2026-09-01', 'description' => 'Hosting 2025']);
    $new = Charge::factory()->for($customer)->create(['amount' => 150000, 'currency' => Currency::Try, 'due_date' => '2026-10-01', 'description' => 'Hosting 2026']);
    $usd = Charge::factory()->for($customer)->create(['amount' => 20000, 'currency' => Currency::Usd, 'due_date' => '2026-08-01']);

    mcpAs($admin, McpAccess::ReadWrite)
        ->tool(RecordPayment::class, ['customer_id' => $customer->id, 'amount_minor' => 300000, 'currency' => 'TRY', 'payment_method_id' => $method->id, 'note' => ' EFT '])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('customer', 'Acme')
            ->where('paid_on', '2026-10-28')
            ->where('payment_method', 'Havale')
            ->where('amount.formatted', '3.000 TL')
            ->where('allocated.amount_minor', 250000)
            ->where('added_to_credit_balance.amount_minor', 50000)
            ->where('allocations.0.description', 'Hosting 2025')
            ->where('allocations.0.charge_status', 'paid')
            ->where('allocations.1.description', 'Hosting 2026')
            ->etc());

    $payment = Payment::query()->sole();

    expect($payment)
        ->recorded_by->toBe($admin->id)
        ->amount->toBe(300000)
        ->note->toBe('EFT')
        ->and($old->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($new->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($usd->refresh()->status)->toBe(ChargeStatus::Open)
        ->and($customer->creditBalances()['TRY']->amount)->toBe(50000);
});

it('takes the payment date when given', function () {
    $customer = Customer::factory()->create();

    mcpAs(User::factory()->admin()->create(), McpAccess::ReadWrite)
        ->tool(RecordPayment::class, ['customer_id' => $customer->id, 'amount_minor' => 1000, 'currency' => 'USD', 'payment_method_id' => PaymentMethod::factory()->create()->id, 'paid_on' => '2026-10-25'])
        ->assertOk();

    expect(Payment::query()->sole())
        ->paid_on->toDateString()->toBe('2026-10-25')
        ->currency->toBe(Currency::Usd);
});

it('refuses a payment with invalid input', function (Closure $arguments) {
    $customer = Customer::factory()->create();
    $method = PaymentMethod::factory()->create();

    mcpAs(User::factory()->admin()->create(), McpAccess::ReadWrite)
        ->tool(RecordPayment::class, $arguments($customer, $method))
        ->assertHasErrors();

    expect(Payment::query()->count())->toBe(0);
})->with([
    'zero amount' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 0, 'currency' => 'TRY', 'payment_method_id' => $m->id],
    'negative amount' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => -500, 'currency' => 'TRY', 'payment_method_id' => $m->id],
    'fractional amount' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 15.5, 'currency' => 'TRY', 'payment_method_id' => $m->id],
    'text amount' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => '1.500', 'currency' => 'TRY', 'payment_method_id' => $m->id],
    'no amount' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'currency' => 'TRY', 'payment_method_id' => $m->id],
    'unknown currency' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 1000, 'currency' => 'GBP', 'payment_method_id' => $m->id],
    'no currency' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 1000, 'payment_method_id' => $m->id],
    'no method' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 1000, 'currency' => 'TRY'],
    'unknown method' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 1000, 'currency' => 'TRY', 'payment_method_id' => 999],
    'archived method' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 1000, 'currency' => 'TRY', 'payment_method_id' => PaymentMethod::factory()->archived()->create()->id],
    'archived customer' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => Customer::factory()->archived()->create()->id, 'amount_minor' => 1000, 'currency' => 'TRY', 'payment_method_id' => $m->id],
    'bad date' => fn (Customer $c, PaymentMethod $m) => ['customer_id' => $c->id, 'amount_minor' => 1000, 'currency' => 'TRY', 'payment_method_id' => $m->id, 'paid_on' => '28.10.2026'],
]);

it('lists the active payment methods and users in the input descriptions', function () {
    PaymentMethod::factory()->create(['name' => 'Havale']);
    PaymentMethod::factory()->archived()->create(['name' => 'Çek']);
    User::factory()->deactivated()->create(['name' => 'Eski Çalışan']);

    $token = app(CreateMcpToken::class)->handle(User::factory()->admin()->create(['name' => 'Ayşe']), 'Ofis', McpAccess::ReadWrite);

    $tools = collect($this->withToken($token)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []])
        ->json('result.tools'))->keyBy('name');

    expect($tools['record-payment']['inputSchema']['properties']['payment_method_id']['description'])
        ->toContain('Havale')->not->toContain('Çek')
        ->and($tools['create-task']['inputSchema']['properties']['assigned_to']['description'])
        ->toContain('Ayşe')->not->toContain('Eski Çalışan');
});
