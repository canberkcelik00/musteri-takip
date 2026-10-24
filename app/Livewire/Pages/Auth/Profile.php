<?php

namespace App\Livewire\Pages\Auth;

use App\Actions\Account\ChangePassword;
use App\Actions\Account\UpdateProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = $this->user();

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function saveProfile(UpdateProfile $updateProfile): void
    {
        $user = $this->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $updateProfile->handle($user, $this->name, $this->email);

        $this->dispatch('notify', message: __('ui.profile.saved'));
    }

    public function changePassword(ChangePassword $changePassword): void
    {
        $this->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $changePassword->handle($this->user(), $this->current_password, $this->password);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('notify', message: __('ui.profile.password_changed'));
    }

    public function render(): View
    {
        return view('livewire.pages.auth.profile')->title(__('ui.profile.title'));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
