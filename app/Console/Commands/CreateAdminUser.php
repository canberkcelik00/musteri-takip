<?php

namespace App\Console\Commands;

use App\Actions\EnsureAdminUser;
use App\Actions\Users\SyncRolesAndPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-admin')]
#[Description('Create the first panel user from the ADMIN_* environment values')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnsureAdminUser $ensureAdminUser, SyncRolesAndPermissions $syncRolesAndPermissions): int
    {
        $syncRolesAndPermissions->handle();

        $user = $ensureAdminUser->handle();

        if ($user === null) {
            $this->components->warn(__('admin.skipped'));
        } elseif ($user->wasRecentlyCreated) {
            $this->components->info(__('admin.created', ['email' => $user->email]));
        } else {
            $this->components->info(__('admin.exists', ['email' => $user->email]));
        }

        return self::SUCCESS;
    }
}
