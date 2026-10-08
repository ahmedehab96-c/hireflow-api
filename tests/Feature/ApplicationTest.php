<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create();
        Sanctum::actingAs($this->candidate);
    }

    public function test_candidate_can_create_application(): void
    {
        $job = Job::factory()->create();

        $this->postJson('/api/applications', ['job_id' => $job->id, 'notes' => 'Referred by a friend.'])
            ->assertCreated()
            ->assertJsonPath('data.job_id', $job->id)
            ->assertJsonPath('data.status', 'saved')
            ->assertJsonPath('data.applied_at', null)
            ->assertJsonPath('data.job.company.id', $job->company_id);

        $this->assertDatabaseHas('applications', ['user_id' => $this->candidate->id, 'job_id' => $job->id]);
    }

    public function test_applied_status_records_applied_at_automatically(): void
    {
        $job = Job::factory()->create();

        $response = $this->postJson('/api/applications', ['job_id' => $job->id, 'status' => 'applied'])->assertCreated();

        $this->assertNotNull($response->json('data.applied_at'));
    }

    public function test_application_validation_failures(): void
    {
        $closed = Job::factory()->closed()->create();

        $this->postJson('/api/applications', [])->assertUnprocessable()->assertJsonValidationErrors(['job_id']);
        $this->postJson('/api/applications', ['job_id' => $closed->id])->assertUnprocessable()->assertJsonValidationErrors(['job_id']);
        $this->postJson('/api/applications', ['job_id' => Job::factory()->create()->id, 'status' => 'dreaming'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_candidate_cannot_track_same_job_twice(): void
    {
        $job = Job::factory()->create();
        Application::factory()->for($this->candidate)->for($job)->create();

        $this->postJson('/api/applications', ['job_id' => $job->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id']);
    }

    public function test_candidate_lists_only_own_applications(): void
    {
        Application::factory()->count(2)->for($this->candidate)->create();
        Application::factory()->count(3)->create();

        $this->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_candidate_can_view_update_and_delete_own_application(): void
    {
        $application = Application::factory()->for($this->candidate)->create();

        $this->getJson("/api/applications/{$application->id}")->assertOk()->assertJsonPath('data.id', $application->id);

        $this->putJson("/api/applications/{$application->id}", [
            'status' => 'technical_interview',
            'interview_at' => '2026-11-01 10:00:00',
            'notes' => 'Prepare system design.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'technical_interview')
            ->assertJsonPath('data.notes', 'Prepare system design.');

        $this->deleteJson("/api/applications/{$application->id}")->assertOk();
        $this->assertModelMissing($application);
    }

    public function test_candidate_cannot_access_another_candidates_application(): void
    {
        $other = Application::factory()->create(['notes' => 'private']);

        $this->getJson("/api/applications/{$other->id}")->assertNotFound();
        $this->putJson("/api/applications/{$other->id}", ['status' => 'hired'])->assertNotFound();
        $this->deleteJson("/api/applications/{$other->id}")->assertNotFound();

        $this->assertDatabaseHas('applications', ['id' => $other->id, 'status' => 'saved', 'notes' => 'private']);
    }

    public function test_update_validation_failures(): void
    {
        $application = Application::factory()->for($this->candidate)->create();

        $this->putJson("/api/applications/{$application->id}", ['status' => 'unknown', 'interview_at' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'interview_at']);
    }

    public function test_admin_cannot_use_candidate_application_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/applications')->assertForbidden();
    }
}
