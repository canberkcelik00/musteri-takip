<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\Currency;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditUser;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Task;
use App\Models\User;
use App\Support\Ui\Navigation;
use Livewire\Livewire;

it('changes the name and role of a user and leaves the e-mail alone', function () {
    $developer = User::factory()->developer()->create(['name' => 'Eski Ad', 'email' => 'ayni@example.com']);

    Livewire::actingAs(User::factory()->admin()->create())->test(EditUser::class, ['user' => $developer->id])
        ->assertSet('name', 'Eski Ad')
        ->assertSet('role', 'developer')
        ->assertSee('ayni@example.com')
        ->set('name', 'Yeni Ad')
        ->set('role', 'admin')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('users-changed')
        ->assertDispatched('close-window');

    $developer->refresh();

    expect($developer->name)->toBe('Yeni Ad')
        ->and($developer->hasRole('admin'))->toBeTrue()
        ->and($developer->email)->toBe('ayni@example.com');
});

it('lets an administrator rename their own account without touching the role', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ben']);

    Livewire::actingAs($admin)->test(EditUser::class, ['user' => $admin->id])
        ->set('name', 'Ben Yeni')
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->fresh()->name)->toBe('Ben Yeni')->and($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('refuses an unknown role and an empty name', function () {
    $developer = User::factory()->developer()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(EditUser::class, ['user' => $developer->id])
        ->set('name', '')
        ->set('role', 'owner')
        ->call('save')
        ->assertHasErrors(['name', 'role']);
});

it('forbids a developer the edit window', function () {
    $other = User::factory()->developer()->create();

    Livewire::actingAs(User::factory()->developer()->create())->test(EditUser::class, ['user' => $other->id])->assertForbidden();
});

it('deletes a user who has done nothing', function () {
    $developer = User::factory()->developer()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test(Confirm::class, ['action' => 'delete-user', 'id' => $developer->id])
        ->call('confirm')
        ->assertDispatched('users-changed')
        ->assertDispatched('close-window');

    expect(User::query()->find($developer->id))->toBeNull();
});

it('does not delete a user named on a money record and says to deactivate instead', function () {
    $developer = User::factory()->developer()->create();
    app(RecordPayment::class)->handle($developer, Customer::factory()->create(), [
        'amount' => 1000,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::factory()->create()->id,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())->test(Confirm::class, ['action' => 'delete-user', 'id' => $developer->id])
        ->call('confirm')
        ->assertHasErrors('form')
        ->assertSee(__('users.has_records'));

    expect(User::query()->find($developer->id))->not->toBeNull();
});

it('does not delete a user who has a task', function (string $column) {
    $developer = User::factory()->developer()->create();
    Task::factory()->create([$column => $developer->id, 'assigned_to' => $column === 'assigned_to' ? $developer->id : User::factory()]);

    Livewire::actingAs(User::factory()->admin()->create())->test(Confirm::class, ['action' => 'delete-user', 'id' => $developer->id])
        ->call('confirm')
        ->assertHasErrors('form')
        ->assertSee(__('users.has_records'));

    expect(User::query()->find($developer->id))->not->toBeNull();
})->with(['assigned' => 'assigned_to', 'created' => 'created_by', 'completed' => 'completed_by']);

it('does not delete the signed-in user or the last active administrator', function () {
    $admin = User::factory()->admin()->create();
    $last = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Confirm::class, ['action' => 'delete-user', 'id' => $admin->id])
        ->call('confirm')
        ->assertHasErrors('form');

    $actor = User::factory()->admin()->deactivated()->create();
    $admin->forceFill(['deactivated_at' => now()])->save();

    Livewire::actingAs($actor)->test(Confirm::class, ['action' => 'delete-user', 'id' => $last->id])
        ->call('confirm')
        ->assertHasErrors('form');

    expect(User::query()->find($admin->id))->not->toBeNull()->and(User::query()->find($last->id))->not->toBeNull();
});

it('forbids a developer the delete confirmation', function () {
    $other = User::factory()->developer()->create();

    Livewire::actingAs(User::factory()->developer()->create())->test(Confirm::class, ['action' => 'delete-user', 'id' => $other->id])->assertForbidden();
});

it('marks only the settings item of the current page in the menu', function () {
    $this->actingAs(User::factory()->admin()->create());

    $active = fn (string $url): array => collect(Navigation::entries($url))
        ->flatMap(fn (array $entry): array => $entry['items'] ?? [$entry])
        ->where('active', true)
        ->pluck('url')
        ->all();

    expect($active(route('app.users')))->toBe([route('app.users')])
        ->and($active(route('app.settings')))->toBe([route('app.settings')])
        ->and($active(route('app.servers')))->toBe([route('app.servers')])
        ->and($active(route('app.customers.index').'/5'))->toBe([route('app.customers.index')]);
});
