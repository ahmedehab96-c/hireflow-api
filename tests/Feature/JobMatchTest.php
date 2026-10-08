<?php

namespace Tests\Feature;

use App\Models\Cv;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use App\Services\AI\Exceptions\AiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakesAi;
use Tests\TestCase;

class JobMatchTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $candidate;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create();
        Sanctum::actingAs($this->candidate);
        $this->job = Job::factory()->create(['title' => 'Senior Laravel Developer']);
        $this->job->skills()->attach(Skill::factory()->create(['name' => 'Laravel']));
    }

    private function match(): array
    {
        return [
            'match_score' => 83,
            'summary' => 'Strong backend match.',
            'matched_skills' => ['Laravel', 'PHP'],
            'missing_skills' => ['Kubernetes'],
            'matching_experience' => ['Led Laravel API team'],
            'recommendations' => ['Highlight API performance work'],
        ];
    }

    public function test_candidate_can_match_own_cv_with_job(): void
    {
        $ai = $this->fakeAi($this->match());
        $cv = Cv::factory()->for($this->candidate)->analyzed()->create();

        $this->postJson("/api/jobs/{$this->job->id}/match", ['cv_id' => $cv->id])
            ->assertOk()
            ->assertJsonPath('data.match_score', 83)
            ->assertJsonPath('data.matched_skills', ['Laravel', 'PHP'])
            ->assertJsonPath('data.missing_skills', ['Kubernetes'])
            ->assertJsonPath('data.job.id', $this->job->id)
            ->assertJsonPath('data.cv.id', $cv->id)
            ->assertJsonMissingPath('data.cv.extracted_text');

        $prompt = $ai->calls[0]['prompt'];
        $this->assertStringContainsString('Senior Laravel Developer', $prompt);
        $this->assertStringContainsString('Required skills: Laravel', $prompt);
        $this->assertStringContainsString($cv->extracted_text, $prompt);
    }

    public function test_matching_requires_a_valid_cv(): void
    {
        $ai = $this->fakeAi($this->match());

        $this->postJson("/api/jobs/{$this->job->id}/match", [])->assertUnprocessable()->assertJsonValidationErrors(['cv_id']);
        $this->postJson("/api/jobs/{$this->job->id}/match", ['cv_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors(['cv_id']);
        $this->postJson("/api/jobs/{$this->job->id}/match", ['cv_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['cv_id']);

        $this->assertSame([], $ai->calls);
    }

    public function test_candidate_cannot_use_another_candidates_cv(): void
    {
        $ai = $this->fakeAi($this->match());
        $other = Cv::factory()->create();

        $this->postJson("/api/jobs/{$this->job->id}/match", ['cv_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cv_id'])
            ->assertJsonMissingPath('data');

        $this->assertSame([], $ai->calls);
    }

    public function test_inactive_job_cannot_be_matched(): void
    {
        $this->fakeAi($this->match());
        $cv = Cv::factory()->for($this->candidate)->create();
        $closed = Job::factory()->closed()->create();

        $this->postJson("/api/jobs/{$closed->id}/match", ['cv_id' => $cv->id])->assertNotFound();
    }

    public function test_ai_failure_during_matching_is_handled_gracefully(): void
    {
        $this->fakeAi(new AiException('The AI service is busy right now. Please try again in a minute.', 503));
        $cv = Cv::factory()->for($this->candidate)->create();

        $this->postJson("/api/jobs/{$this->job->id}/match", ['cv_id' => $cv->id])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The AI service is busy right now. Please try again in a minute.');
    }

    public function test_admin_cannot_use_matching(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/jobs/{$this->job->id}/match", ['cv_id' => 1])->assertForbidden();
    }
}
