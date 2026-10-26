<?php

namespace App\Actions\Account;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    /** Failed attempts per e-mail and address before the sign-in is locked for a while. */
    public const int MAX_ATTEMPTS = 5;

    /**
     * Sign the user in, or throw a validation error on the `email` field: the credentials do
     * not match, the account is deactivated or has no password yet (an unaccepted invitation),
     * or there were too many failed attempts. The first three look the same to the caller, so
     * the form does not tell which e-mail addresses have an account.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password, bool $remember, string $ip): void
    {
        $key = Str::transliterate(Str::lower($email).'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $credentials = [
            'email' => $email,
            'password' => $password,
            fn (Builder $query) => $query->whereNull('deactivated_at')->whereNotNull('password'),
        ];

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        session()->regenerate();
    }
}
