<?php

namespace Tests\Feature;

use App\Models\Cv;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use App\Services\AI\Exceptions\AiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiProvider;
use Tests\Support\FakesAi;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $candidate;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create();
        Sanctum::actingAs($this->candidate);
        $this->job = Job::factory()->create(['title' => 'Network Support Engineer']);
        $this->job->skills()->attach(Skill::factory()->create(['name' => 'Networking']));
    }

    /** 3 HR, 3 technical, 2 behavioral — deliberately out of order to check sorting. */
    private function questionsResponse(): array
    {
        $plan = ['hr', 'hr', 'hr', 'technical', 'technical', 'technical', 'behavioral', 'behavioral'];
        $questions = [];
        foreach (array_reverse($plan, true) as $i => $category) {
            $questions[] = ['question' => 'Question '.($i + 1).'?', 'category' => $category, 'order' => $i + 1];
        }

        return ['questions' => $questions];
    }

    private function evaluation(int $score = 8): array
    {
        return [
            'score' => $score,
            'feedback' => 'Clear and relevant answer.',
            'strengths' => ['Structured', 'Specific'],
            'weaknesses' => ['No metrics'],
            'improved_answer' => 'A stronger version of the answer.',
        ];
    }

    private function finalFeedback(): array
    {
        return [
            'technical' => 8.5,
            'communication' => 8,
            'relevance' => 9.04,
            'summary' => 'Solid performance.',
            'strengths' => ['Clear structure'],
            'areas_to_improve' => ['Quantify impact'],
            'recommended_practice' => ['Practise STAR answers'],
            'recommendation' => 'Ready for first-round interviews.',
        ];
    }

    /** Routes each AI call by the schema it asks for, like the real service does. */
    private function fakeInterviewAi(int $answerScore = 8): FakeAiProvider
    {
        return $this->fakeAi(function ($system, $prompt, $schema) use ($answerScore) {
            $keys = array_keys($schema['properties']);

            return match (true) {
                $keys === ['questions'] => $this->questionsResponse(),
                in_array('improved_answer', $keys, true) => $this->evaluation($answerScore),
                default => $this->finalFeedback(),
            };
        });
    }

    private function startedInterview(): Interview
    {
        $interview = Interview::factory()->for($this->candidate)->for($this->job)->create();
        $this->postJson("/api/interviews/{$interview->id}/start")->assertOk();

        return $interview->fresh();
    }

    public function test_candidate_can_create_interview(): void
    {
        $cv = Cv::factory()->for($this->candidate)->create();

        $this->postJson('/api/interviews', ['job_id' => $this->job->id, 'cv_id' => $cv->id, 'type' => 'mixed'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'mixed')
            ->assertJsonPath('data.job.title', 'Network Support Engineer')
            ->assertJsonPath('data.cv.id', $cv->id)
            ->assertJsonPath('data.questions', []);

        $this->assertDatabaseHas('interviews', ['user_id' => $this->candidate->id, 'job_id' => $this->job->id, 'cv_id' => $cv->id]);
    }

    public function test_validation_errors(): void
    {
        $closed = Job::factory()->closed()->create();

        $this->postJson('/api/interviews', [])->assertUnprocessable()->assertJsonValidationErrors(['job_id', 'cv_id', 'type']);
        $this->postJson('/api/interviews', ['job_id' => $this->job->id, 'type' => 'casual'])->assertUnprocessable()->assertJsonValidationErrors(['type']);
        $this->postJson('/api/interviews', ['job_id' => $closed->id, 'type' => 'hr'])->assertUnprocessable()->assertJsonValidationErrors(['job_id']);
        $this->postJson('/api/interviews', ['job_id' => 999999, 'type' => 'hr'])->assertUnprocessable()->assertJsonValidationErrors(['job_id']);

        $this->assertDatabaseCount('interviews', 0);
    }

    public function test_interview_requires_valid_cv(): void
    {
        $othersCv = Cv::factory()->create();

        $this->postJson('/api/interviews', ['job_id' => $this->job->id, 'cv_id' => 999999, 'type' => 'technical'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cv_id']);
        $this->postJson('/api/interviews', ['job_id' => $this->job->id, 'cv_id' => $othersCv->id, 'type' => 'technical'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cv_id']);

        $this->assertDatabaseCount('interviews', 0);
    }

    public function test_start_generates_questions_and_hides_future_ones(): void
    {
        $ai = $this->fakeInterviewAi();
        $cv = Cv::factory()->for($this->candidate)->analyzed()->create();
        $interview = Interview::factory()->for($this->candidate)->for($this->job)->create(['cv_id' => $cv->id]);

        $this->postJson("/api/interviews/{$interview->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress', ['total' => 8, 'answered' => 0])
            // Only the current question is visible.
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonPath('data.questions.0.order', 1)
            ->assertJsonPath('data.questions.0.question', 'Question 1?')
            ->assertJsonPath('data.questions.0.category', 'hr');

        $this->assertDatabaseCount('interview_questions', 8);
        $this->assertNotNull($interview->fresh()->started_at);

        $prompt = $ai->calls[0]['prompt'];
        $this->assertStringContainsString('Network Support Engineer', $prompt);
        $this->assertStringContainsString('Required skills: Networking', $prompt);
        $this->assertStringContainsString('3 hr, 3 technical, 2 behavioral', $prompt);
        $this->assertStringContainsString('Backend engineer focused on PHP.', $prompt); // CV analysis used

        // Starting twice is rejected.
        $this->postJson("/api/interviews/{$interview->id}/start")->assertStatus(409);
        $this->assertDatabaseCount('interview_questions', 8);
    }

    public function test_single_type_interview_keeps_its_category(): void
    {
        $this->fakeInterviewAi();
        $interview = Interview::factory()->for($this->candidate)->for($this->job)->create(['type' => 'technical']);

        $this->postJson("/api/interviews/{$interview->id}/start")->assertOk();

        $this->assertSame(['technical'], $interview->questions()->distinct()->pluck('category')->all());
    }

    public function test_ai_failure_on_start_leaves_interview_pending(): void
    {
        $this->fakeAi(new AiException);
        $interview = Interview::factory()->for($this->candidate)->for($this->job)->create();

        $this->postJson("/api/interviews/{$interview->id}/start")->assertStatus(502)->assertJsonPath('success', false);

        $this->assertTrue($interview->fresh()->isPending());
        $this->assertDatabaseCount('interview_questions', 0);
    }

    public function test_candidate_answers_current_question_and_gets_feedback(): void
    {
        $ai = $this->fakeInterviewAi(7);
        $interview = $this->startedInterview();
        $first = $interview->questions()->first();

        $this->postJson("/api/interviews/{$interview->id}/questions/{$first->id}/answer", ['answer' => '  I would check the cable, IP config and DNS.  '])
            ->assertOk()
            ->assertJsonPath('data.question.answer.score', 7)
            ->assertJsonPath('data.question.answer.answer', 'I would check the cable, IP config and DNS.')
            ->assertJsonPath('data.question.answer.strengths', ['Structured', 'Specific'])
            ->assertJsonPath('data.question.answer.weaknesses', ['No metrics'])
            ->assertJsonPath('data.question.answer.improved_answer', 'A stronger version of the answer.')
            ->assertJsonPath('data.interview.progress.answered', 1)
            // The next question becomes visible only now.
            ->assertJsonCount(2, 'data.interview.questions');

        $this->assertStringContainsString('<answer>', $ai->calls[1]['prompt']);

        // Same question again → conflict.
        $this->postJson("/api/interviews/{$interview->id}/questions/{$first->id}/answer", ['answer' => 'Again'])->assertStatus(409);
    }

    public function test_questions_must_be_answered_in_order(): void
    {
        $this->fakeInterviewAi();
        $interview = $this->startedInterview();
        $third = $interview->questions()->where('order', 3)->first();

        $this->postJson("/api/interviews/{$interview->id}/questions/{$third->id}/answer", ['answer' => 'Skipping ahead'])
            ->assertStatus(409);
        $this->assertDatabaseCount('interview_answers', 0);
    }

    public function test_answer_must_belong_to_the_interview(): void
    {
        $this->fakeInterviewAi();
        $mine = $this->startedInterview();
        $otherOfMine = Interview::factory()->for($this->candidate)->started(2)->create();
        $foreignQuestion = $otherOfMine->questions()->first();

        // A question from a different interview (even the candidate's own) is not found under this interview.
        $this->postJson("/api/interviews/{$mine->id}/questions/{$foreignQuestion->id}/answer", ['answer' => 'Mixed up'])
            ->assertNotFound();
        $this->assertDatabaseCount('interview_answers', 0);
    }

    public function test_candidate_cannot_answer_another_users_interview_question(): void
    {
        $ai = $this->fakeInterviewAi();
        $theirs = Interview::factory()->started(2)->create();
        $question = $theirs->questions()->first();

        $this->postJson("/api/interviews/{$theirs->id}/questions/{$question->id}/answer", ['answer' => 'Not my interview'])
            ->assertNotFound();
        $this->assertDatabaseCount('interview_answers', 0);
        $this->assertSame([], $ai->calls);
    }

    public function test_answer_validation_errors(): void
    {
        $this->fakeInterviewAi();
        $interview = $this->startedInterview();
        $first = $interview->questions()->first();
        $url = "/api/interviews/{$interview->id}/questions/{$first->id}/answer";

        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['answer']);
        $this->postJson($url, ['answer' => '   '])->assertUnprocessable()->assertJsonValidationErrors(['answer']);
        $this->postJson($url, ['answer' => str_repeat('a', 5001)])->assertUnprocessable()->assertJsonValidationErrors(['answer']);
    }

    public function test_cannot_answer_before_start_or_after_completion(): void
    {
        $this->fakeInterviewAi();
        $pending = Interview::factory()->for($this->candidate)->create();
        $completed = Interview::factory()->for($this->candidate)->started(1)->create(['status' => 'completed']);
        $question = $completed->questions()->first();

        $this->postJson("/api/interviews/{$completed->id}/questions/{$question->id}/answer", ['answer' => 'Late answer'])->assertStatus(409);
        $this->postJson("/api/interviews/{$pending->id}/complete")->assertStatus(409);
    }

    public function test_completed_interview_gets_final_score(): void
    {
        $this->fakeInterviewAi(8);
        $interview = $this->startedInterview();

        // Answer all 8 questions with a score of 8 each.
        foreach ($interview->questions as $question) {
            $this->postJson("/api/interviews/{$interview->id}/questions/{$question->id}/answer", ['answer' => "Answer to {$question->question}"])->assertOk();
        }

        $this->postJson("/api/interviews/{$interview->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_score', 80)
            ->assertJsonPath('data.feedback.technical', 8.5)
            ->assertJsonPath('data.feedback.communication', 8)
            ->assertJsonPath('data.feedback.relevance', 9) // rounded to one decimal
            ->assertJsonPath('data.feedback.areas_to_improve', ['Quantify impact'])
            ->assertJsonPath('data.feedback.recommendation', 'Ready for first-round interviews.')
            ->assertJsonCount(8, 'data.questions');

        $fresh = $interview->fresh();
        $this->assertSame(80, $fresh->total_score);
        $this->assertNotNull($fresh->completed_at);

        $this->postJson("/api/interviews/{$interview->id}/complete")->assertStatus(409);
    }

    public function test_finishing_early_counts_unanswered_questions_as_zero(): void
    {
        $this->fakeInterviewAi(10);
        $interview = $this->startedInterview();

        $this->postJson("/api/interviews/{$interview->id}/complete")->assertUnprocessable(); // nothing answered yet

        $first = $interview->questions()->first();
        $this->postJson("/api/interviews/{$interview->id}/questions/{$first->id}/answer", ['answer' => 'Only answer'])->assertOk();

        $this->postJson("/api/interviews/{$interview->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.total_score', 13) // 10 / 80 points
            ->assertJsonPath('data.feedback.answered', 1)
            ->assertJsonPath('data.feedback.total_questions', 8);
    }

    public function test_candidate_cannot_access_another_users_interview(): void
    {
        $theirs = Interview::factory()->started(2)->create();

        $this->getJson("/api/interviews/{$theirs->id}")->assertNotFound();
        $this->postJson("/api/interviews/{$theirs->id}/start")->assertNotFound();
        $this->postJson("/api/interviews/{$theirs->id}/complete")->assertNotFound();
        $this->deleteJson("/api/interviews/{$theirs->id}")->assertNotFound();
        $this->assertModelExists($theirs);

        Interview::factory()->for($this->candidate)->create();
        $this->getJson('/api/interviews')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_candidate_can_list_view_and_delete_own_interviews(): void
    {
        $this->fakeInterviewAi();
        $interview = $this->startedInterview();

        $this->getJson('/api/interviews')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $interview->id)
            ->assertJsonPath('data.items.0.progress', ['total' => 8, 'answered' => 0])
            ->assertJsonMissingPath('data.items.0.questions');

        $this->getJson("/api/interviews/{$interview->id}")->assertOk()->assertJsonCount(1, 'data.questions');

        $this->deleteJson("/api/interviews/{$interview->id}")->assertOk();
        $this->assertModelMissing($interview);
        $this->assertDatabaseCount('interview_questions', 0);
    }

    public function test_unauthorized_requests_are_rejected(): void
    {
        $interview = Interview::factory()->create();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/interviews')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/interviews')->assertForbidden();
        $this->postJson('/api/interviews', ['job_id' => $this->job->id, 'type' => 'hr'])->assertForbidden();
        $this->postJson("/api/interviews/{$interview->id}/start")->assertForbidden();
    }
}
