<?php

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Exceptions\TaskMailNotSent;
use App\Mail\TaskAssignedMail;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('e-mails the assignee when a task is created for someone else', function () {
    Mail::fake();
    $actor = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);
    $other = User::factory()->developer()->create();

    app(CreateTask::class)->handle($actor, ['title' => 'Formu düzelt', 'assigned_to' => $other->id]);

    Mail::assertSent(TaskAssignedMail::class, fn (TaskAssignedMail $mail) => $mail->hasTo($other->email)
        && $mail->assignedBy->is($actor));
    Mail::assertSentCount(1);
});

it('does not e-mail when a task is given to oneself', function () {
    Mail::fake();
    $actor = User::factory()->developer()->create();

    app(CreateTask::class)->handle($actor, ['title' => 'Kendime']);
    app(CreateTask::class)->handle($actor, ['title' => 'Kendime 2', 'assigned_to' => $actor->id]);

    Mail::assertNothingSent();
});

it('e-mails the new assignee when it changes, and nobody when it stays', function () {
    Mail::fake();
    $actor = User::factory()->admin()->create();
    $first = User::factory()->developer()->create();
    $second = User::factory()->developer()->create();
    $task = Task::factory()->create(['assigned_to' => $first->id]);

    app(UpdateTask::class)->handle($actor, $task, ['title' => 'Aynı kişi', 'assigned_to' => $first->id]);
    Mail::assertNothingSent();

    app(UpdateTask::class)->handle($actor, $task, ['title' => 'Başka kişi', 'assigned_to' => $second->id]);
    Mail::assertSent(TaskAssignedMail::class, fn (TaskAssignedMail $mail) => $mail->hasTo($second->email));
    Mail::assertSentCount(1);
});

it('does not e-mail when the actor takes a task over', function () {
    Mail::fake();
    $actor = User::factory()->developer()->create();
    $task = Task::factory()->create();

    app(UpdateTask::class)->handle($actor, $task, ['title' => 'Benim', 'assigned_to' => $actor->id]);

    Mail::assertNothingSent();
});

it('keeps the task saved and raises when the e-mail cannot be sent', function () {
    $actor = User::factory()->admin()->create();
    $other = User::factory()->developer()->create();

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP kapalı'));

    expect(fn () => app(CreateTask::class)->handle($actor, ['title' => 'Kaydedilir', 'assigned_to' => $other->id]))
        ->toThrow(TaskMailNotSent::class, 'SMTP kapalı');

    expect(Task::query()->where('title', 'Kaydedilir')->value('assigned_to'))->toBe($other->id);
});

it('shows the task and carries no money', function () {
    $actor = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);
    $other = User::factory()->developer()->create();
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    $task = Task::factory()->for($customer)->create([
        'title' => 'Form mail atmıyor',
        'notes' => 'Acil bakılacak',
        'assigned_to' => $other->id,
        'due_date' => '2026-11-05',
    ]);

    $mail = new TaskAssignedMail($task, $actor);

    $mail->assertHasSubject('Size yeni iş atandı: Form mail atmıyor');
    $mail->assertSeeInHtml('Form mail atmıyor');
    $mail->assertSeeInHtml('Logi');
    $mail->assertSeeInHtml('05.11.2026');
    $mail->assertSeeInHtml('Acil bakılacak');
    $mail->assertSeeInHtml('Ayşe Yönetici');
    $mail->assertSeeInHtml(url('/tasks'));
    $mail->assertSeeInText('Form mail atmıyor');
    $mail->assertDontSeeInHtml('₺');
    $mail->assertDontSeeInHtml('TRY');
});
