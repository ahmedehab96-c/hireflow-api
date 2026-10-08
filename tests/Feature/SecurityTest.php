<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Cross-cutting checks: role boundaries for every admin route, headers, CORS and error hygiene. */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{string, string}> [method, uri] for every admin API route */
    private function adminRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $r) => str_starts_with($r->uri(), 'api/admin/'))
            ->map(fn (RouteDefinition $r) => [collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->first(), preg_replace('/\{[^}]+\}/', '1', $r->uri())])
            ->values()
            ->all();
    }

    public function test_every_admin_route_rejects_guests_and_candidates(): void
    {
        $routes = $this->adminRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as [$method, $uri]) {
            $this->json($method, "/{$uri}")->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($routes as [$method, $uri]) {
            $this->json($method, "/{$uri}")->assertForbidden();
        }
    }

    public function test_candidate_only_routes_reject_admins(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach (['/api/applications', '/api/cvs', '/api/interviews', '/api/activity'] as $uri) {
            $this->getJson($uri)->assertForbidden();
        }
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_cors_allows_only_configured_origins(): void
    {
        // Two origins, so the request origin is checked per request (with a single origin the
        // library always echoes that one origin, which browsers still reject for other sites).
        config(['cors.allowed_origins' => ['https://app.hireflow.example', 'https://demo.hireflow.example']]);

        $this->withHeaders(['Origin' => 'https://app.hireflow.example'])->getJson('/api/jobs')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.hireflow.example');

        $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/jobs')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_server_errors_do_not_leak_details_in_production_mode(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/_boom', fn () => throw new \RuntimeException('secret internal detail'));

        $response = $this->getJson('/api/_boom')->assertStatus(500)->assertJsonPath('message', 'Server error.');

        $this->assertStringNotContainsString('secret internal detail', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_user_payloads_never_include_password_or_tokens(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $body = $this->getJson('/api/me')->assertOk()->getContent();

        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('remember_token', $body);
    }
}
