<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates one Owner, one Manager and one Caretaker account.
 * An account that already exists is left untouched, so re-running never resets a password.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::ROLES as $role) {
            $username = strtolower($role);

            if (User::where('username', $username)->exists()) {
                $this->command?->line("User '{$username}' already exists, skipped.");
                continue;
            }

            $password = config("narciana.seed_passwords.{$username}");
            $generated = blank($password);
            if ($generated) {
                $password = Str::password(12, symbols: false);
            }

            User::create([
                'full_name' => $role,
                'username' => $username,
                'password_hash' => $password, // hashed by the model's 'hashed' cast
                'role' => $role,
                'contact_no' => '00000000000', // placeholder: update in User Accounts
                'is_active' => true,
            ]);

            $this->command?->info($generated
                ? "Created '{$username}' with password: {$password}   (shown only once, write it down)"
                : "Created '{$username}' with the password from .env.");
        }
    }
}
