<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create(['username' => 'owner']);
    }

    private function validUser(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'New Caretaker',
            'username' => 'caretaker2',
            'role' => 'Caretaker',
            'contact_no' => '0917 555 0100',
            'password' => 'long-enough',
            'password_confirmation' => 'long-enough',
        ], $overrides);
    }

    // ---------- Access ----------

    public function test_owner_sees_user_list_and_role_access_table(): void
    {
        $this->actingAs($this->owner)->get('/users')
            ->assertOk()
            ->assertSee('owner')
            ->assertSee('Role access')
            ->assertSee('Move-out: approve refund / release');
    }

    public function test_manager_and_caretaker_get_403_on_user_accounts(): void
    {
        foreach (['Manager', 'Caretaker'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get('/users')->assertForbidden()->assertSee('You do not have access');
            $this->actingAs($user)->post('/users', $this->validUser())->assertForbidden();
        }
        $this->assertSame(0, User::where('username', 'caretaker2')->count());
    }

    public function test_menu_shows_only_items_for_the_users_role(): void
    {
        $this->actingAs($this->owner)->get('/')->assertSee('User Accounts')->assertSee('Settings &amp; Backup', false);

        $this->actingAs(User::factory()->role('Manager')->create())->get('/')
            ->assertSee('Billing')->assertSee('Reports')
            ->assertDontSee('User Accounts')->assertDontSee('Settings &amp; Backup', false);

        $this->actingAs(User::factory()->role('Caretaker')->create())->get('/')
            ->assertSee('Rooms &amp; Inquiries', false)->assertSee('Concerns')
            ->assertDontSee('Billing')->assertDontSee('Payments')->assertDontSee('User Accounts');
    }

    // ---------- Add ----------

    public function test_owner_can_add_a_user_with_a_hashed_password(): void
    {
        $this->actingAs($this->owner)->post('/users', $this->validUser(['username' => '  Caretaker2 ']))
            ->assertRedirect('/users')->assertSessionHas('status');

        $user = User::where('username', 'caretaker2')->firstOrFail(); // trimmed and lower-cased
        $this->assertSame('Caretaker', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('long-enough', $user->password_hash);
        $this->assertTrue(Hash::check('long-enough', $user->password_hash));
        $this->assertDatabaseHas('tbl_audit_log', [
            'user_id' => $this->owner->user_id, 'action' => 'User created', 'record_id' => $user->user_id,
        ]);
    }

    public function test_add_user_validation(): void
    {
        $this->actingAs($this->owner)->post('/users', $this->validUser([
            'username' => 'owner',          // taken
            'role' => 'Admin',              // not a role
            'contact_no' => 'call me',      // letters
            'password' => 'short',          // too short and not confirmed
        ]))->assertSessionHasErrors(['username', 'role', 'contact_no', 'password']);

        $this->actingAs($this->owner)->post('/users', $this->validUser(['username' => 'has space']))
            ->assertSessionHasErrors(['username']);

        $this->assertSame(1, User::count());
    }

    // ---------- Edit ----------

    public function test_owner_can_edit_a_user_and_blank_password_keeps_the_old_one(): void
    {
        $user = User::factory()->role('Caretaker')->create(['username' => 'ct', 'password_hash' => 'original-pass']);

        $this->actingAs($this->owner)->put("/users/{$user->user_id}", [
            'full_name' => 'Renamed', 'username' => 'ct', 'role' => 'Manager', 'contact_no' => '0918 000 0000',
            'password' => '', 'password_confirmation' => '',
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertSame('Renamed', $user->full_name);
        $this->assertSame('Manager', $user->role);
        $this->assertTrue(Hash::check('original-pass', $user->password_hash));
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'User changed', 'record_id' => $user->user_id]);
        $this->assertDatabaseMissing('tbl_audit_log', ['action' => 'Password reset']);
    }

    public function test_owner_can_reset_a_users_password(): void
    {
        $user = User::factory()->role('Manager')->create(['username' => 'mg']);

        $this->actingAs($this->owner)->put("/users/{$user->user_id}", [
            'full_name' => $user->full_name, 'username' => 'mg', 'role' => 'Manager', 'contact_no' => $user->contact_no,
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/users');

        $this->assertTrue(Hash::check('brand-new-pass', $user->refresh()->password_hash));
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Password reset', 'record_id' => $user->user_id]);
    }

    public function test_owner_cannot_change_own_role(): void
    {
        $this->actingAs($this->owner)->get("/users/{$this->owner->user_id}/edit")
            ->assertOk()->assertSee('You cannot change your own role.');

        $this->actingAs($this->owner)->put("/users/{$this->owner->user_id}", [
            'full_name' => 'Owner', 'username' => 'owner', 'role' => 'Caretaker', 'contact_no' => '0917',
        ])->assertSessionHasErrors(['role' => 'You cannot change your own role.']);

        $this->assertSame('Owner', $this->owner->refresh()->role);
    }

    public function test_owner_can_edit_own_details_without_sending_a_role(): void
    {
        $this->actingAs($this->owner)->put("/users/{$this->owner->user_id}", [
            'full_name' => 'Owner', 'username' => 'owner', 'contact_no' => '0917 111 2222',
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $this->assertSame('0917 111 2222', $this->owner->refresh()->contact_no);
        $this->assertSame('Owner', $this->owner->role);
    }

    // ---------- Deactivate / reactivate ----------

    public function test_owner_can_deactivate_and_reactivate_a_user(): void
    {
        $user = User::factory()->role('Manager')->create();

        $this->actingAs($this->owner)->patch("/users/{$user->user_id}/deactivate")->assertRedirect('/users');
        $this->assertFalse($user->refresh()->is_active);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'User deactivated', 'record_id' => $user->user_id]);

        $this->actingAs($this->owner)->patch("/users/{$user->user_id}/activate")->assertRedirect('/users');
        $this->assertTrue($user->refresh()->is_active);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'User activated', 'record_id' => $user->user_id]);
    }

    public function test_owner_cannot_deactivate_own_account(): void
    {
        $this->actingAs($this->owner)->patch("/users/{$this->owner->user_id}/deactivate")->assertForbidden();

        $this->assertTrue($this->owner->refresh()->is_active);
        $this->assertSame(0, AuditLog::where('action', 'User deactivated')->count());
    }

    public function test_list_shows_the_right_buttons(): void
    {
        $inactive = User::factory()->role('Caretaker')->inactive()->create(['username' => 'gone']);

        $this->actingAs($this->owner)->get('/users')
            ->assertSee('Deactivated')
            ->assertSee(route('users.activate', $inactive), false)
            ->assertDontSee(route('users.deactivate', $this->owner), false);
    }
}
