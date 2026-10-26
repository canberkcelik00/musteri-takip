<?php

namespace App\Livewire\Pages\Auth;

use App\Actions\Users\AcceptInvitation as AcceptInvitationAction;
use App\Exceptions\InvalidInvitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Where an invited person chooses a password: the page of the signed invitation link. A link that was replaced by
 * a newer invitation, expired, already used or belongs to a deactivated user shows a notice instead of the form.
 */
#[Layout('layouts.guest')]
class AcceptInvitation extends Component
{
    #[Locked]
    public int $userId = 0;

    #[Locked]
    public int $stamp = 0;

    #[Locked]
    public int $expires = 0;

    public string $password = '';

    public string $password_confirmation = '';

    public bool $invalid = false;

    public function mount(User $user, AcceptInvitationAction $accept): void
    {
        $this->userId = $user->id;
        $this->stamp = request()->integer('stamp');
        $this->expires = request()->integer('expires');
        $this->invalid = ! $accept->isUsable($user, $this->stamp, $this->expires);
    }

    public function accept(AcceptInvitationAction $accept): void
    {
        $this->validate(['password' => ['required', 'confirmed', Password::min(8)]]);

        try {
            $user = $accept->handle(User::query()->findOrFail($this->userId), $this->stamp, $this->expires, $this->password);
        } catch (InvalidInvitation) {
            $this->invalid = true;

            return;
        }

        Auth::login($user);
        session()->regenerate();

        $this->redirect(route('app.today'));
    }

    public function render(): View
    {
        return view('livewire.pages.auth.accept-invitation')->title(__('users.accept_title'));
    }
}
