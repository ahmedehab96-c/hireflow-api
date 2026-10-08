<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_candidate_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $company = Company::factory()->create();

        $this->getJson('/api/admin/companies')->assertForbidden()->assertJsonPath('success', false);
        $this->postJson('/api/admin/companies', ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/admin/companies/{$company->id}")->assertForbidden();
        $this->postJson('/api/admin/jobs', [])->assertForbidden();
        $this->postJson('/api/admin/skills', ['name' => 'Go'])->assertForbidden();

        $this->assertModelExists($company);
    }

    public function test_admin_can_manage_companies(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/admin/companies', ['name' => 'Acme Labs', 'website' => 'https://acme.example'])
            ->assertCreated()->json('data.id');

        $this->getJson('/api/admin/companies')->assertOk()->assertJsonPath('data.items.0.name', 'Acme Labs');
        $this->putJson("/api/admin/companies/{$id}", ['industry' => 'Robotics'])
            ->assertOk()->assertJsonPath('data.industry', 'Robotics')->assertJsonPath('data.name', 'Acme Labs');
        $this->deleteJson("/api/admin/companies/{$id}")->assertOk();

        $this->assertDatabaseMissing('companies', ['id' => $id]);
    }

    public function test_company_with_jobs_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $job = Job::factory()->create();

        $this->deleteJson("/api/admin/companies/{$job->company_id}")->assertStatus(409);
    }

    public function test_admin_can_manage_jobs_with_skills(): void
    {
        $this->actingAsAdmin();
        $company = Company::factory()->create();
        $skills = Skill::factory()->count(3)->create();

        $response = $this->postJson('/api/admin/jobs', [
            'company_id' => $company->id,
            'title' => 'Platform Engineer',
            'description' => 'Run our platform.',
            'employment_type' => 'full_time',
            'salary_min' => 20000,
            'salary_max' => 25000,
            'skill_ids' => $skills->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonPath('data.currency', 'AED')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(3, 'data.skills');

        $id = $response->json('data.id');

        $this->putJson("/api/admin/jobs/{$id}", ['status' => 'closed', 'skill_ids' => [$skills[0]->id]])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonCount(1, 'data.skills');

        // Admin still sees closed jobs; the public board does not.
        $this->getJson('/api/admin/jobs')->assertJsonPath('data.meta.total', 1);
        $this->getJson("/api/jobs/{$id}")->assertNotFound();

        $this->deleteJson("/api/admin/jobs/{$id}")->assertOk();
        $this->assertDatabaseMissing('jobs', ['id' => $id]);
        $this->assertDatabaseCount('job_skill', 0);
    }

    public function test_job_with_applications_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $application = Application::factory()->create();

        $this->deleteJson("/api/admin/jobs/{$application->job_id}")->assertStatus(409);
    }

    public function test_job_validation_failures(): void
    {
        $this->actingAsAdmin();
        $job = Job::factory()->create(['salary_min' => 10000, 'salary_max' => 15000]);

        $this->postJson('/api/admin/jobs', ['company_id' => 999, 'status' => 'archived', 'skill_ids' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id', 'title', 'description', 'status', 'skill_ids.0']);

        // Partial update is checked against the stored minimum.
        $this->putJson("/api/admin/jobs/{$job->id}", ['salary_max' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['salary_max']);
    }

    public function test_admin_can_manage_skills(): void
    {
        $this->actingAsAdmin();
        Skill::factory()->create(['name' => 'Laravel']);

        $this->postJson('/api/admin/skills', ['name' => 'Laravel'])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $id = $this->postJson('/api/admin/skills', ['name' => 'Go'])->assertCreated()->json('data.id');
        $this->putJson("/api/admin/skills/{$id}", ['name' => 'Golang'])->assertOk()->assertJsonPath('data.name', 'Golang');
        $this->getJson("/api/admin/skills/{$id}")->assertOk();
        $this->deleteJson("/api/admin/skills/{$id}")->assertOk();

        $this->assertDatabaseMissing('skills', ['id' => $id]);
    }
}
