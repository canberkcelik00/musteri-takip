<?php

use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\ReopenTask;
use App\Actions\Tasks\UpdateTask;
use App\Exceptions\InvalidTask;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

describe('create', function () {
    it('gives the task to the actor when no assignee is named', function () {
        $actor = User::factory()->developer()->create();

        $task = app(CreateTask::class)->handle($actor, ['title' => 'Formu düzelt']);

        expect($task->assigned_to)->toBe($actor->id)
            ->and($task->created_by)->toBe($actor->id)
            ->and($task->completed_at)->toBeNull()
            ->and($task->customer_id)->toBeNull();
    });

    it('stores the customer, the due date and the notes, and turns blank notes into null', function () {
        $actor = User::factory()->developer()->create();
        $customer = Customer::factory()->create();

        $task = app(CreateTask::class)->handle($actor, [
            'title' => 'SSL yenile',
            'notes' => '',
            'customer_id' => $customer->id,
            'due_date' => '2026-11-05',
        ]);

        expect($task->customer_id)->toBe($customer->id)
            ->and($task->due_date->toDateString())->toBe('2026-11-05')
            ->and($task->notes)->toBeNull();
    });

    it('assigns the task to another user', function () {
        $actor = User::factory()->admin()->create();
        $other = User::factory()->developer()->create();

        $task = app(CreateTask::class)->handle($actor, ['title' => 'Kur', 'assigned_to' => $other->id]);

        expect($task->assigned_to)->toBe($other->id)
            ->and($task->created_by)->toBe($actor->id);
    });

    it('refuses a deactivated assignee', function () {
        $actor = User::factory()->admin()->create();
        $inactive = User::factory()->developer()->create(['deactivated_at' => now()]);

        expect(fn () => app(CreateTask::class)->handle($actor, ['title' => 'Kur', 'assigned_to' => $inactive->id]))
            ->toThrow(InvalidTask::class, 'pasif');

        expect(Task::query()->count())->toBe(0);
    });

    it('refuses an archived customer', function () {
        $actor = User::factory()->admin()->create();
        $customer = Customer::factory()->archived()->create();

        expect(fn () => app(CreateTask::class)->handle($actor, ['title' => 'Kur', 'customer_id' => $customer->id]))
            ->toThrow(InvalidTask::class, 'Arşivlenmiş');
    });
});

describe('update', function () {
    it('changes the fields and keeps who created the task', function () {
        $actor = User::factory()->admin()->create();
        $task = app(CreateTask::class)->handle($actor, ['title' => 'Eski']);

        app(UpdateTask::class)->handle($actor, $task, [
            'title' => 'Yeni',
            'notes' => 'Not',
            'assigned_to' => $actor->id,
            'due_date' => '2026-12-01',
        ]);

        $task->refresh();

        expect($task->title)->toBe('Yeni')
            ->and($task->notes)->toBe('Not')
            ->and($task->due_date->toDateString())->toBe('2026-12-01')
            ->and($task->created_by)->toBe($actor->id);
    });

    it('refuses to hand the task to a deactivated user but lets one who became inactive keep it', function () {
        $actor = User::factory()->admin()->create();
        $inactive = User::factory()->developer()->create(['deactivated_at' => now()]);
        $task = Task::factory()->create(['assigned_to' => $inactive->id]);

        app(UpdateTask::class)->handle($actor, $task, ['title' => 'Aynı kişide kalır', 'assigned_to' => $inactive->id]);

        expect($task->fresh()->title)->toBe('Aynı kişide kalır');

        $mine = Task::factory()->create(['assigned_to' => $actor->id]);

        expect(fn () => app(UpdateTask::class)->handle($actor, $mine, ['title' => 'x', 'assigned_to' => $inactive->id]))
            ->toThrow(InvalidTask::class);
    });

    it('refuses to move the task to an archived customer', function () {
        $actor = User::factory()->admin()->create();
        $task = Task::factory()->create(['assigned_to' => $actor->id]);
        $archived = Customer::factory()->archived()->create();

        expect(fn () => app(UpdateTask::class)->handle($actor, $task, [
            'title' => 'x',
            'customer_id' => $archived->id,
            'assigned_to' => $actor->id,
        ]))->toThrow(InvalidTask::class);
    });

    it('lets a task keep a customer archived since', function () {
        $actor = User::factory()->admin()->create();
        $archived = Customer::factory()->archived()->create();
        $task = Task::factory()->create(['assigned_to' => $actor->id, 'customer_id' => $archived->id]);

        app(UpdateTask::class)->handle($actor, $task, [
            'title' => 'Yeni başlık',
            'customer_id' => $archived->id,
            'assigned_to' => $actor->id,
        ]);

        expect($task->fresh()->title)->toBe('Yeni başlık');
    });
});

describe('complete and reopen', function () {
    it('marks the task done by the actor', function () {
        $creator = User::factory()->admin()->create();
        $finisher = User::factory()->developer()->create();
        $task = app(CreateTask::class)->handle($creator, ['title' => 'Bitir']);

        $this->travelTo(now()->addHour());
        app(CompleteTask::class)->handle($finisher, $task);

        $task->refresh();

        expect($task->isCompleted())->toBeTrue()
            ->and($task->completed_by)->toBe($finisher->id)
            ->and($task->completed_at)->not->toBeNull();
    });

    it('does not rewrite who finished a task done twice', function () {
        $first = User::factory()->admin()->create();
        $second = User::factory()->developer()->create();
        $task = Task::factory()->create(['assigned_to' => $first->id]);

        app(CompleteTask::class)->handle($first, $task);
        $finishedAt = $task->fresh()->completed_at;

        $this->travelTo(now()->addDay());
        app(CompleteTask::class)->handle($second, $task->fresh());

        $task->refresh();

        expect($task->completed_by)->toBe($first->id)
            ->and($task->completed_at->equalTo($finishedAt))->toBeTrue();
    });

    it('reopens a done task and clears who finished it', function () {
        $task = Task::factory()->completed()->create();

        app(ReopenTask::class)->handle($task);

        $task->refresh();

        expect($task->isCompleted())->toBeFalse()
            ->and($task->completed_by)->toBeNull();
    });
});

describe('delete', function () {
    it('deletes the task', function () {
        $task = Task::factory()->create();

        app(DeleteTask::class)->handle($task);

        expect(Task::query()->find($task->id))->toBeNull();
    });
});

describe('derived state', function () {
    it('tells overdue, due today and later apart on the day', function () {
        $this->travelTo('2026-11-10 10:00:00');

        $overdue = Task::factory()->create(['due_date' => '2026-11-09']);
        $today = Task::factory()->create(['due_date' => '2026-11-10']);
        $later = Task::factory()->create(['due_date' => '2026-11-11']);
        $undated = Task::factory()->create();
        $doneOverdue = Task::factory()->completed()->create(['due_date' => '2026-11-01']);

        expect(Task::overdue()->pluck('id')->all())->toBe([$overdue->id])
            ->and(Task::dueToday()->pluck('id')->all())->toBe([$today->id])
            ->and($overdue->isOverdue())->toBeTrue()
            ->and($today->isOverdue())->toBeFalse()
            ->and($today->isDueToday())->toBeTrue()
            ->and($later->isOverdue() || $later->isDueToday())->toBeFalse()
            ->and($undated->isOverdue())->toBeFalse()
            ->and($doneOverdue->isOverdue())->toBeFalse()
            ->and(Task::open()->count())->toBe(4)
            ->and(Task::completed()->pluck('id')->all())->toBe([$doneOverdue->id]);
    });

    it('lists the earliest due date first and the undated last', function () {
        $undated = Task::factory()->create();
        $later = Task::factory()->create(['due_date' => today()->addDays(5)]);
        $overdue = Task::factory()->overdue()->create();

        expect(Task::orderForList()->pluck('id')->all())->toBe([$overdue->id, $later->id, $undated->id]);
    });

    it('limits to the tasks of one user', function () {
        $user = User::factory()->developer()->create();
        $mine = Task::factory()->create(['assigned_to' => $user->id]);
        Task::factory()->create();

        expect(Task::assignedTo($user)->pluck('id')->all())->toBe([$mine->id])
            ->and($user->assignedTasks()->pluck('id')->all())->toBe([$mine->id]);
    });
});
