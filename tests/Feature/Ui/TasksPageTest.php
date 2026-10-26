<?php

use App\Enums\Permission;
use App\Livewire\Pages\Tasks\Index as TasksIndex;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    $this->me = User::factory()->developer()->create(['name' => 'Ben Kendim']);
    $this->other = User::factory()->developer()->create(['name' => 'Baska Kisi']);
});

it('forbids a role without the tasks permission the page', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);

    $this->actingAs($this->me)->get(route('app.tasks.index'))->assertForbidden();
    Livewire::actingAs($this->me)->test(TasksIndex::class)->assertForbidden();
});

it('shows a developer the page and the menu item', function () {
    $this->actingAs($this->me)->get(route('app.tasks.index'))->assertOk()->assertSee(__('tasks.plural'))->assertSee('ui-nav-pink', false);
});

it('lists only the open tasks assigned to me by default', function () {
    Task::factory()->create(['title' => 'Benim isim', 'assigned_to' => $this->me->id]);
    Task::factory()->completed()->create(['title' => 'Biten isim', 'assigned_to' => $this->me->id]);
    Task::factory()->create(['title' => 'Baskasinin isi', 'assigned_to' => $this->other->id]);

    Livewire::actingAs($this->me)->test(TasksIndex::class)
        ->assertSee('Benim isim')
        ->assertDontSee('Biten isim')
        ->assertDontSee('Baskasinin isi')
        ->assertDontSee('Baska Kisi');
});

it('shows everyones tasks with the assignee, and the done ones on request', function () {
    Task::factory()->create(['title' => 'Benim isim', 'assigned_to' => $this->me->id]);
    Task::factory()->create(['title' => 'Baskasinin isi', 'assigned_to' => $this->other->id]);
    Task::factory()->completed()->create(['title' => 'Biten isim', 'assigned_to' => $this->me->id]);

    Livewire::actingAs($this->me)->test(TasksIndex::class)
        ->set('who', 'all')
        ->assertSee('Benim isim')->assertSee('Baskasinin isi')->assertSee('Baska Kisi')->assertDontSee('Biten isim')
        ->set('status', 'done')
        ->assertSee('Biten isim')->assertDontSee('Benim isim')->assertSee(__('tasks.reopen'))
        ->set('status', 'all')
        ->assertSee('Biten isim')->assertSee('Benim isim');
});

it('marks a deactivated assignee and an archived customer', function () {
    $gone = User::factory()->developer()->deactivated()->create(['name' => 'Eski Calisan']);
    $customer = Customer::factory()->create(['short_name' => 'Eski Musteri']);
    Task::factory()->create(['assigned_to' => $gone->id, 'customer_id' => $customer->id]);
    $customer->archive();

    Livewire::actingAs($this->me)->test(TasksIndex::class)->set('who', 'all')
        ->assertSee('Eski Calisan')->assertSee(__('tasks.inactive_suffix'))
        ->assertSee('Eski Musteri')->assertSee(__('tasks.archived'));
});

it('orders overdue first, then by date, and tasks without a date last', function () {
    Task::factory()->create(['title' => 'Tarihsiz is', 'assigned_to' => $this->me->id]);
    Task::factory()->create(['title' => 'Gelecek is', 'assigned_to' => $this->me->id, 'due_date' => '2026-06-30']);
    Task::factory()->overdue()->create(['title' => 'Gecikmis is', 'assigned_to' => $this->me->id]);
    Task::factory()->dueToday()->create(['title' => 'Bugunluk is', 'assigned_to' => $this->me->id]);

    Livewire::actingAs($this->me)->test(TasksIndex::class)
        ->assertSeeInOrder(['Gecikmis is', 'Bugunluk is', 'Gelecek is', 'Tarihsiz is'])
        ->assertSee(__('tasks.overdue_days', ['count' => 3]))
        ->assertSee(__('tasks.due_today'));
});

it('filters to overdue tasks and to one customer', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    Task::factory()->overdue()->create(['title' => 'Gecikmis is', 'assigned_to' => $this->me->id]);
    Task::factory()->create(['title' => 'Logi isi', 'assigned_to' => $this->me->id, 'customer_id' => $customer->id]);

    Livewire::actingAs($this->me)->test(TasksIndex::class)
        ->call('toggleOverdue')->assertSee('Gecikmis is')->assertDontSee('Logi isi')
        ->call('toggleOverdue')
        ->set('customer', (string) $customer->id)->assertSee('Logi isi')->assertDontSee('Gecikmis is');
});

it('searches the title, the note and the customer name', function () {
    $customer = Customer::factory()->create(['short_name' => 'Mavi Cati']);
    Task::factory()->create(['title' => 'Form maili', 'assigned_to' => $this->me->id]);
    Task::factory()->create(['title' => 'Baska', 'notes' => 'sertifika yenile', 'assigned_to' => $this->me->id]);
    Task::factory()->create(['title' => 'Ucuncu', 'assigned_to' => $this->me->id, 'customer_id' => $customer->id]);

    Livewire::actingAs($this->me)->test(TasksIndex::class)
        ->set('search', 'maili')->assertSee('Form maili')->assertDontSee('Ucuncu')
        ->set('search', 'sertifika')->assertSee('Baska')->assertDontSee('Form maili')
        ->set('search', 'Mavi')->assertSee('Ucuncu')->assertDontSee('Form maili');
});

it('completes a task from the row and drops it from the open list, and reopens it', function () {
    $task = Task::factory()->create(['title' => 'Yapilacak', 'assigned_to' => $this->me->id]);

    Livewire::actingAs($this->me)->test(TasksIndex::class)
        ->call('complete', $task->id)
        ->assertDispatched('notify')
        ->assertDontSee('Yapilacak');

    expect($task->fresh()->completed_by)->toBe($this->me->id);

    Livewire::actingAs($this->me)->test(TasksIndex::class)->set('status', 'done')
        ->assertSee('Yapilacak')
        ->call('reopen', $task->id)
        ->assertDontSee('Yapilacak');

    expect($task->fresh()->isCompleted())->toBeFalse();
});

it('refuses the row actions without the permission', function () {
    $task = Task::factory()->create(['assigned_to' => $this->me->id]);
    $component = Livewire::actingAs($this->me)->test(TasksIndex::class);

    RoleModel::findByName('developer')->revokePermissionTo(Permission::TasksManage->value);

    $component->call('complete', $task->id)->assertForbidden();

    expect($task->fresh()->isCompleted())->toBeFalse();
});

it('shows a hint when nothing matches and the empty state when there are no tasks', function () {
    Livewire::actingAs($this->me)->test(TasksIndex::class)->assertSee(__('tasks.empty_heading'))
        ->set('search', 'yok')->assertSee(__('ui.table.no_results'));
});

it('keeps the query count flat however many tasks there are', function () {
    $count = function (int $tasks): int {
        Task::query()->delete();
        Task::factory()->count($tasks)->create(['assigned_to' => $this->me->id, 'customer_id' => Customer::factory()]);

        $component = Livewire::actingAs($this->me)->test(TasksIndex::class)->set('who', 'all');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('refresh');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(12))->toBe($count(2));
});
