<?php

use App\Actions\Tasks\SendTaskAssignedMail;
use App\Enums\Permission;
use App\Exceptions\TaskMailNotSent;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditTask;
use App\Mail\TaskAssignedMail;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->me = User::factory()->developer()->create(['name' => 'Ben Kendim']);
    $this->colleague = User::factory()->developer()->create(['name' => 'Is Arkadasi']);
});

it('creates a task for myself by default without e-mail', function () {
    Mail::fake();

    Livewire::actingAs($this->me)->test(EditTask::class)
        ->assertSet('assignedTo', (string) $this->me->id)
        ->set('title', 'Sertifikayi yenile')
        ->set('dueDate', '2026-07-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('tasks-changed')
        ->assertDispatched('close-window');

    $task = Task::query()->firstOrFail();

    expect($task->title)->toBe('Sertifikayi yenile')
        ->and($task->assigned_to)->toBe($this->me->id)
        ->and($task->created_by)->toBe($this->me->id)
        ->and($task->due_date?->format('Y-m-d'))->toBe('2026-07-01');
    Mail::assertNothingSent();
});

it('fills the due date from the today, tomorrow and next-week pills by Istanbul days', function () {
    // 21:30 UTC is already the next day in Istanbul.
    $this->travelTo(Carbon\Carbon::parse('2026-12-31 21:30:00', 'UTC'));

    Livewire::actingAs($this->me)->test(EditTask::class)
        ->call('pickDueDate', 'today')
        ->assertSet('dueDate', '2027-01-01')
        ->call('pickDueDate', 'tomorrow')
        ->assertSet('dueDate', '2027-01-02')
        ->call('pickDueDate', 'next-week')
        ->assertSet('dueDate', '2027-01-08')
        ->call('pickDueDate', 'someday')
        ->assertSet('dueDate', '2027-01-08');
});

it('assigns a task to a colleague and e-mails them', function () {
    Mail::fake();

    Livewire::actingAs($this->me)->test(EditTask::class)
        ->set('title', 'Forma bak')
        ->set('assignedTo', (string) $this->colleague->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Task::query()->firstOrFail()->assigned_to)->toBe($this->colleague->id);
    Mail::assertSent(TaskAssignedMail::class, fn (TaskAssignedMail $mail): bool => $mail->hasTo($this->colleague->email));
});

it('opens with the customer filled when a card passes it', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    Livewire::actingAs($this->me)->test(EditTask::class, ['customer' => $customer->id])
        ->assertSet('customerId', (string) $customer->id)
        ->set('title', 'Mail sorunu')
        ->call('save');

    expect(Task::query()->firstOrFail()->customer_id)->toBe($customer->id);
});

it('edits a task and keeps it saved when the e-mail fails', function () {
    $task = Task::factory()->create(['title' => 'Eski', 'assigned_to' => $this->me->id, 'created_by' => $this->me->id]);
    $this->mock(SendTaskAssignedMail::class)->shouldReceive('handle')->andThrow(new TaskMailNotSent(new RuntimeException('smtp down')));

    Livewire::actingAs($this->me)->test(EditTask::class, ['task' => $task->id])
        ->assertSet('title', 'Eski')
        ->set('title', 'Yeni')
        ->set('assignedTo', (string) $this->colleague->id)
        ->call('save')
        ->assertDispatched('tasks-changed')
        ->assertDispatched('close-window')
        ->assertDispatched('notify', message: __('tasks.mail_not_sent', ['message' => 'smtp down']), tone: 'danger');

    expect($task->fresh()->title)->toBe('Yeni')->and($task->fresh()->assigned_to)->toBe($this->colleague->id);
});

it('requires a title and refuses an unknown assignee', function () {
    Livewire::actingAs($this->me)->test(EditTask::class)
        ->set('title', '')
        ->set('assignedTo', '99999')
        ->call('save')
        ->assertHasErrors(['title', 'assignedTo']);

    expect(Task::query()->count())->toBe(0);
});

it('does not offer a deactivated user or an archived customer, but keeps the ones a task has', function () {
    $gone = User::factory()->developer()->deactivated()->create(['name' => 'Eski Calisan']);
    $archived = Customer::factory()->create(['short_name' => 'Arsiv Musteri']);
    $other = Customer::factory()->create(['short_name' => 'Arsiv Baska']);
    $task = Task::factory()->create(['assigned_to' => $gone->id, 'customer_id' => $archived->id]);
    $archived->archive();
    $other->archive();

    Livewire::actingAs($this->me)->test(EditTask::class)
        ->assertDontSee('Eski Calisan')->assertDontSee('Arsiv Musteri')->assertSee('Is Arkadasi');

    Livewire::actingAs($this->me)->test(EditTask::class, ['task' => $task->id])
        ->assertSee('Eski Calisan')->assertSee(__('tasks.inactive_suffix'))->assertSee('Arsiv Musteri')->assertDontSee('Arsiv Baska');
});

it('refuses moving a task to a deactivated user', function () {
    $gone = User::factory()->developer()->deactivated()->create();

    Livewire::actingAs($this->me)->test(EditTask::class)
        ->set('title', 'x')
        ->set('assignedTo', (string) $gone->id)
        ->call('save')
        ->assertHasErrors('form');

    expect(Task::query()->count())->toBe(0);
});

it('names who created and who finished the task and offers Bitti and Yeniden aç', function () {
    $task = Task::factory()->create(['assigned_to' => $this->me->id, 'created_by' => $this->colleague->id]);

    Livewire::actingAs($this->me)->test(EditTask::class, ['task' => $task->id])
        ->assertSee('Is Arkadasi tarafından oluşturuldu')
        ->assertSee(__('tasks.complete'))
        ->call('complete')
        ->assertDispatched('tasks-changed')
        ->assertDispatched('close-window');

    expect($task->fresh()->completed_by)->toBe($this->me->id);

    Livewire::actingAs($this->colleague)->test(EditTask::class, ['task' => $task->id])
        ->assertSee('Ben Kendim tarafından bitirildi')
        ->call('reopen');

    expect($task->fresh()->isCompleted())->toBeFalse();
});

it('checks the window permission on every request', function () {
    $component = Livewire::actingAs($this->me)->test(EditTask::class)->set('title', 'x');

    RoleModel::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);

    $component->call('save')->assertForbidden();

    expect(Task::query()->count())->toBe(0);
});

it('deletes a task after the confirmation', function () {
    $task = Task::factory()->create(['assigned_to' => $this->me->id]);

    Livewire::actingAs($this->me)->test(Confirm::class, ['action' => 'delete-task', 'id' => $task->id])
        ->call('confirm')
        ->assertDispatched('tasks-changed')
        ->assertDispatched('close-window');

    expect(Task::query()->find($task->id))->toBeNull();
});

it('forbids the delete confirmation without the permission', function () {
    $task = Task::factory()->create();
    RoleModel::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);

    Livewire::actingAs($this->me)->test(Confirm::class, ['action' => 'delete-task', 'id' => $task->id])->assertForbidden();
});

it('opens with only the notes filled, cleaned, from a pasted message', function () {
    Customer::factory()->create(['short_name' => 'Mavi Ofis']);
    $message = "[19:49, 26.10.2026] W Müşteri: Mavi Ofis sitesinde banner değişsin\n[19:50, 26.10.2026] W Müşteri: rengi mavi olsun";

    Livewire::actingAs($this->me)->test(EditTask::class, ['pasted' => $message])
        ->assertSet('notes', "Mavi Ofis sitesinde banner değişsin\nrengi mavi olsun")
        ->assertSet('title', '')
        ->assertSet('customerId', '')
        ->assertSet('assignedTo', (string) $this->me->id);
});

it('saves a pasted task once a title is written and refuses it without one', function () {
    Mail::fake();

    $component = Livewire::actingAs($this->me)->test(EditTask::class, ['pasted' => '[19:49, 26.10.2026] W Müşteri: banner değişsin'])
        ->call('save')
        ->assertHasErrors(['title' => 'required']);

    expect(Task::query()->count())->toBe(0);

    $component->set('title', 'Banner')->call('save')->assertHasNoErrors()->assertDispatched('close-window');

    $task = Task::query()->firstOrFail();

    expect($task->title)->toBe('Banner')
        ->and($task->notes)->toBe('banner değişsin')
        ->and($task->created_by)->toBe($this->me->id);
    Mail::assertNothingSent();
});

it('e-mails a colleague when a pasted task is assigned to them', function () {
    Mail::fake();

    Livewire::actingAs($this->me)->test(EditTask::class, ['pasted' => 'Forma bak'])
        ->set('title', 'Form')
        ->set('assignedTo', (string) $this->colleague->id)
        ->call('save');

    Mail::assertSent(TaskAssignedMail::class, fn (TaskAssignedMail $mail): bool => $mail->hasTo($this->colleague->email));
});

it('adds a text pasted into the notes under them, cleaned', function () {
    Livewire::actingAs($this->me)->test(EditTask::class, ['pasted' => '[19:49, 26.10.2026] W Müşteri: banner değişsin'])
        ->call('appendPasted', "[19:55, 26.10.2026] W Müşteri: bir de form\n\n\n")
        ->assertSet('notes', "banner değişsin\nbir de form")
        ->call('appendPasted', '  ')
        ->assertSet('notes', "banner değişsin\nbir de form");
});

it('fills an empty notes field from a paste into it and keeps the notes within 5000 characters', function () {
    Livewire::actingAs($this->me)->test(EditTask::class)
        ->call('appendPasted', 'ilk parça')
        ->assertSet('notes', 'ilk parça')
        ->call('appendPasted', str_repeat('a', 6000))
        ->tap(fn ($component) => expect(mb_strlen($component->get('notes')))->toBe(5000));
});

it('puts the paste listener in the page only for a user who can manage tasks', function () {
    $this->actingAs($this->me)->get(route('app.profile'))->assertOk()->assertSee('x-on:paste.window', false);

    RoleModel::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);

    $this->actingAs(User::query()->findOrFail($this->me->id))->get(route('app.profile'))->assertOk()->assertDontSee('x-on:paste.window', false);
});

it('does not open the task window from a paste without the permission', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);

    Livewire::actingAs($this->me)->test(WindowHost::class)
        ->dispatch('open-window', name: 'edit-task', params: ['pasted' => 'Banner değişsin'])
        ->assertSet('name', null);
});
