<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(['username' => 'manager1', 'password_hash' => 'secret-pass'] + $attributes);
    }

    public function test_login_page_is_shown(): void
    {
        $this->get('/login')->assertOk()->assertSee('Username')->assertSee('Log in');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_user_can_log_in_with_username_and_password(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['username' => 'manager1', 'password' => 'secret-pass'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('tbl_audit_log', [
            'user_id' => $user->user_id, 'action' => 'Login', 'table_name' => 'tbl_user', 'record_id' => $user->user_id,
        ]);
    }

    public function test_username_is_not_case_sensitive(): void
    {
        $this->makeUser();

        $this->post('/login', ['username' => 'Manager1', 'password' => 'secret-pass'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_and_unknown_username_give_the_same_message(): void
    {
        $this->makeUser();

        $this->from('/login')->post('/login', ['username' => 'manager1', 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Wrong username or password.']);

        $this->from('/login')->post('/login', ['username' => 'nobody', 'password' => 'wrong'])
            ->assertSessionHasErrors(['username' => 'Wrong username or password.']);

        $this->assertGuest();
        $this->assertSame(0, AuditLog::count());
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->post('/login', ['username' => 'manager1', 'password' => 'secret-pass'])
            ->assertSessionHasErrors(['username' => 'This account is deactivated. Please contact the Owner.']);

        $this->assertGuest();
    }

    public function test_sixth_quick_attempt_is_blocked_even_with_the_right_password(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'manager1', 'password' => 'wrong']);
        }

        $this->post('/login', ['username' => 'manager1', 'password' => 'secret-pass'])
            ->assertSessionHasErrors('username');

        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_is_audited(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('tbl_audit_log', ['user_id' => $user->user_id, 'action' => 'Logout']);
    }

    public function test_logged_in_user_visiting_login_goes_to_dashboard(): void
    {
        $this->actingAs($this->makeUser())->get('/login')->assertRedirect('/');
    }

    public function test_user_deactivated_mid_session_is_logged_out_on_next_click(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/')->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'This account is deactivated. Please contact the Owner.']);
        $this->assertGuest();
    }

    public function test_session_lifetime_is_fifteen_minutes(): void
    {
        $this->assertSame(15, config('session.lifetime'));
    }

    public function test_expired_session_shows_the_inactivity_message(): void
    {
        // A browser that was logged in (cookie) but has no session any more = timed out.
        $this->withCookie(LoginController::SEEN_COOKIE, '1')
            ->get('/')->assertRedirect(route('login', ['timeout' => 1]));

        $this->withCookie(LoginController::SEEN_COOKIE, '1')
            ->get(route('login', ['timeout' => 1]))
            ->assertOk()
            ->assertSee('You were logged out after 15 minutes of inactivity.');
    }

    public function test_inactivity_message_is_not_shown_without_a_previous_login(): void
    {
        $this->get(route('login', ['timeout' => 1]))->assertOk()->assertDontSee('minutes of inactivity');
    }

    public function test_pages_include_the_idle_warning_and_timer(): void
    {
        $this->actingAs($this->makeUser())->get('/')
            ->assertSee('id="idle-warning"', false)
            ->assertSee('Stay logged in')
            ->assertSee('js/idle-timer.js', false)
            ->assertSee('data-lifetime="15"', false)
            ->assertSee('data-keep-alive-url="'.route('keep-alive').'"', false)
            ->assertSee('timeout=1', false);
    }

    public function test_login_page_has_no_idle_timer(): void
    {
        $this->get('/login')->assertDontSee('idle-timer.js', false);
    }

    public function test_stay_logged_in_refreshes_the_session(): void
    {
        $this->actingAs($this->makeUser())->post('/keep-alive')->assertNoContent();
    }

    public function test_keep_alive_does_not_work_for_guests_or_deactivated_users(): void
    {
        $this->post('/keep-alive')->assertRedirect('/login');

        $this->actingAs($this->makeUser(['is_active' => false]))->post('/keep-alive')->assertRedirect('/login');
        $this->assertGuest();
    }
}
