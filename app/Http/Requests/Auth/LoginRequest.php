<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Failed tries allowed per minute for one username on one device (decision 9). */
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Check the username and password and return the user, or throw a validation error.
     * Wrong username and wrong password give the same message, so usernames cannot be guessed.
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $username = (string) $this->input('username');
        $password = (string) $this->input('password');

        $driver = DB::connection()->getDriverName();
        $query = $driver === 'mysql'
            ? User::whereRaw('BINARY username = ?', [$username])
            : User::whereRaw('username = ? COLLATE BINARY', [$username]);

        /** @var User|null $user */
        $user = $query->first();

        if (! $user || ! Hash::check($password, $user->password_hash ?? $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
        }

        RateLimiter::clear($this->throttleKey());

        if (! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'This account is deactivated. Please contact the Owner.']);
        }

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
