<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Makes throwaway users for automated tests.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => 'Test Manager',
            'username' => fake()->unique()->bothify('user####'),
            'password_hash' => 'password',
            'role' => 'Manager',
            'contact_no' => '09170000000',
            'is_active' => true,
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role, 'full_name' => "Test {$role}"]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
