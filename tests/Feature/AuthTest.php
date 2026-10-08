<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_candidate(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Candidate',
            'email' => 'New.User@Example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'new.user@example.com')
            ->assertJsonPath('data.user.role', 'candidate')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role'], 'token', 'token_type']]);

        $this->assertDatabaseHas('users', ['email' => 'new.user@example.com', 'role' => 'candidate']);
    }

    public function test_registration_cannot_escalate_to_admin(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('data.user.role', 'candidate');
    }

    public function test_registration_validation_failures(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret123'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Wrong1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validation_failures(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('success', false);
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->getJson('/api/applications')->assertUnauthorized();
        $this->getJson('/api/admin/companies')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset the cached guard so the next request re-authenticates from scratch.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
