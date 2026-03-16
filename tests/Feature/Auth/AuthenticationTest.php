<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'employee_code' => $user->employee_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_requiring_a_password_change_are_redirected_to_the_forced_password_change_screen(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $response = $this->post('/login', [
            'employee_code' => $user->employee_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('password.force.edit', absolute: false));
    }

    public function test_users_requiring_a_password_change_can_not_open_protected_pages(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertRedirect(route('password.force.edit', absolute: false));
    }

    public function test_forced_password_change_updates_password_and_clears_security_flags(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $this->actingAs($user)->get('/profile');

        $response = $this->actingAs($user)
            ->put(route('password.force.update'), [
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ]);

        $response->assertRedirect('/profile');

        $user->refresh();

        $this->assertFalse((bool) $user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'employee_code' => $user->employee_code,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
