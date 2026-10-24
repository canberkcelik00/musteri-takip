<?php

namespace App\Livewire\Pages\Auth;

use App\Actions\Account\AuthenticateUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(AuthenticateUser $authenticate): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $authenticate->handle($this->email, $this->password, $this->remember, (string) request()->ip());

        $this->redirectIntended(default: route('app.today'));
    }

    public function render(): View
    {
        return view('livewire.pages.auth.login')->title(__('ui.login.title'));
    }
}
