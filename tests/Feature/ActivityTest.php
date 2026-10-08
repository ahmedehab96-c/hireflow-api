<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cv;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakesAi;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create();
        Sanctum::actingAs($this->candidate);
    }

    public function test_candidate_actions_appear_in_timeline_newest_first(): void
    {
        $job = Job::factory()->create(['title' => 'Backend Engineer']);
        $this->postJson('/api/applications', ['job_id' => $job->id, 'status' => 'applied'])->assertCreated();
        $application = $this->candidate->applications()->first();
        $this->putJson("/api/applications/{$application->id}", ['status' => 'interview'])->assertOk();

        $this->fakeAi(['match_score' => 77, 'summary' => '', 'matched_skills' => [], 'missing_skills' => [], 'matching_experience' => [], 'recommendations' => []]);
        $cv = Cv::factory()->for($this->candidate)->create();
        $this->postJson("/api/jobs/{$job->id}/match", ['cv_id' => $cv->id])->assertOk();

        $this->getJson('/api/activity')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonPath('data.items.0.type', 'job_match_completed')
            ->assertJsonPath('data.items.0.properties.score', 77)
            ->assertJsonPath('data.items.0.subject', ['type' => 'job', 'id' => $job->id])
            ->assertJsonPath('data.items.1.type', 'application_status_changed')
            ->assertJsonPath('data.items.1.description', 'Backend Engineer: Applied → Interview')
            ->assertJsonPath('data.items.2.type', 'application_created')
            ->assertJsonPath('data.items.2.description', 'Applied to Backend Engineer');
    }

    public function test_timeline_only_shows_own_activity(): void
    {
        $other = User::factory()->create();
        Application::factory()->for($other)->create();
        $other->activities()->create(['type' => 'application_created', 'description' => 'Someone else']);

        $this->getJson('/api/activity')->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    public function test_admins_and_guests_cannot_read_candidate_timeline(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/activity')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/activity')->assertUnauthorized();
    }
}
