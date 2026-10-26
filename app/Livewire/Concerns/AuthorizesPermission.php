<?php

namespace App\Livewire\Concerns;

use App\Enums\Permission;
use Illuminate\Support\Facades\Auth;

/**
 * Makes a window or page refuse a user who lacks its permission, on the first render and on every later request
 * (Livewire runs `boot{Trait}` each time), so hiding a button is never the only guard. A window's permission is
 * the one registered for it in `config('ui.window_permissions')`; a page names its own by overriding
 * `requiredPermission()`.
 */
trait AuthorizesPermission
{
    public function bootAuthorizesPermission(): void
    {
        $permission = $this->requiredPermission();

        if ($permission !== null) {
            $this->authorizePermission($permission);
        }
    }

    protected function authorizePermission(Permission $permission): void
    {
        abort_unless(Auth::user()?->can($permission->value), 403);
    }

    protected function requiredPermission(): ?Permission
    {
        /** @var array<string, string> $windows */
        $windows = config('ui.windows', []);
        $name = array_search(static::class, $windows, true);

        if ($name === false) {
            return null;
        }

        $permission = config("ui.window_permissions.{$name}");

        return is_string($permission) ? Permission::from($permission) : null;
    }
}
