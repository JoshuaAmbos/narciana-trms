<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MyPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_open_my_password(): void
    {
        foreach (User::ROLES as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/my-password')->assertOk()->assertSee('Change my password');
        }
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->role('Caretaker')->create(['password_hash' => 'old-password']);

        $this->actingAs($user)->put('/my-password', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect('/my-password')->assertSessionHas('status', 'Your password was changed.');

        $this->assertTrue(Hash::check('new-password-1', $user->refresh()->password_hash));
        $this->assertDatabaseHas('tbl_audit_log', [
            'user_id' => $user->user_id, 'action' => 'Password changed', 'record_id' => $user->user_id,
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password_hash' => 'old-password']);

        $this->actingAs($user)->put('/my-password', [
            'current_password' => 'not-it',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors(['current_password' => 'Your current password is not correct.']);

        $this->assertTrue(Hash::check('old-password', $user->refresh()->password_hash));
    }

    public function test_new_password_rules(): void
    {
        $user = User::factory()->create(['password_hash' => 'old-password']);

        $this->actingAs($user)->put('/my-password', [
            'current_password' => 'old-password', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put('/my-password', [
            'current_password' => 'old-password', 'password' => 'old-password', 'password_confirmation' => 'old-password',
        ])->assertSessionHasErrors(['password' => 'The new password must be different from the current one.']);

        $this->actingAs($user)->put('/my-password', [
            'current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'typo',
        ])->assertSessionHasErrors('password');
    }

    public function test_guest_cannot_change_a_password(): void
    {
        $this->put('/my-password', ['current_password' => 'x', 'password' => 'y', 'password_confirmation' => 'y'])
            ->assertRedirect('/login');
    }
}
