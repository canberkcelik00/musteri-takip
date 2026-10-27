<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Exceptions\InvalidUserChange;
use App\Models\CalendarEvent;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    /**
     * Delete a user who has done nothing yet. Someone named on a payment, charge, quote, task or calendar event stays (deactivate them
     * instead), so the history keeps its "who". A user cannot delete themself, and the last active administrator
     * stays.
     *
     * @throws InvalidUserChange
     */
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw InvalidUserChange::ownAccount();
        }

        if ($user->isActive() && $user->hasRole(Role::Admin->value) && User::activeAdministratorCount() <= 1) {
            throw InvalidUserChange::lastAdministrator();
        }

        if (self::hasRecords($user)) {
            throw InvalidUserChange::hasRecords();
        }

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->delete();
        });
    }

    public static function hasRecords(User $user): bool
    {
        return Payment::query()->where('recorded_by', $user->id)->exists()
            || Charge::query()->where('cancelled_by', $user->id)->orWhere('updated_by', $user->id)->exists()
            || Quote::query()->where('accepted_by', $user->id)->exists()
            || Task::query()->where(fn ($query) => $query
                ->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhere('completed_by', $user->id))->exists()
            || CalendarEvent::query()->where(fn ($query) => $query
                ->where('created_by', $user->id)
                ->orWhereHas('attendees', fn ($attendees) => $attendees->whereKey($user->id)))->exists();
    }
}
