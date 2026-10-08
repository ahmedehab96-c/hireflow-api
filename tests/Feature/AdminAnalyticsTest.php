<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_platform_statistics(): void
    {
        $admin = User::factory()->admin()->create();
        $candidates = User::factory()->count(2)->create();
        $company = Company::factory()->create();
        $active = Job::factory()->for($company)->create();
        Job::factory()->for($company)->closed()->create();
        Application::factory()->for($candidates[0])->for($active)->create(['status' => 'offer']);
        Application::factory()->for($candidates[1])->for($active)->create(['status' => 'hired']);
        Interview::factory()->for($candidates[0])->for($active)->create(['status' => 'completed', 'total_score' => 80]);
        Interview::factory()->for($candidates[1])->for($active)->create(['status' => 'completed', 'total_score' => 61]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.users.total', 3)
            ->assertJsonPath('data.users.candidates', 2)
            ->assertJsonPath('data.users.admins', 1)
            ->assertJsonPath('data.companies', 1)
            ->assertJsonPath('data.jobs.total', 2)
            ->assertJsonPath('data.jobs.active', 1)
            ->assertJsonPath('data.jobs.closed', 1)
            ->assertJsonPath('data.applications.total', 2)
            ->assertJsonPath('data.applications.by_status.offer', 1)
            ->assertJsonPath('data.applications.by_status.saved', 0)
            ->assertJsonPath('data.offers', 1)
            ->assertJsonPath('data.hired', 1)
            ->assertJsonPath('data.interviews.completed', 2)
            ->assertJsonPath('data.interviews.average_score', 71)
            ->assertJsonCount(6, 'data.trend')
            ->assertJsonPath('data.trend.5.applications', 2);
    }

    public function test_admin_can_search_and_filter_users_without_private_data(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Platform Admin']));
        User::factory()->create(['name' => 'Layla Candidate', 'email' => 'layla@example.com']);
        User::factory()->create(['name' => 'Omar Candidate']);

        $this->getJson('/api/admin/users?role=candidate')->assertOk()->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/admin/users?search=layla')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.email', 'layla@example.com')
            ->assertJsonPath('data.items.0.applications_count', 0)
            ->assertJsonMissingPath('data.items.0.password')
            ->assertJsonMissingPath('data.items.0.remember_token');

        $this->getJson('/api/admin/users?role=superuser')->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_candidates_cannot_see_admin_analytics_or_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }
}
