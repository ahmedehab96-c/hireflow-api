<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakesAi;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create();
        Sanctum::actingAs($this->candidate);
    }

    public function test_creating_an_application_notifies_the_candidate(): void
    {
        $job = Job::factory()->create(['title' => 'Data Analyst']);

        $this->postJson('/api/applications', ['job_id' => $job->id, 'status' => 'applied'])->assertCreated();

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.kind', 'application_created')
            ->assertJsonPath('data.items.0.title', 'Application tracked')
            ->assertJsonPath('data.items.0.subject.type', 'application')
            ->assertJsonPath('data.items.0.read_at', null);
    }

    public function test_status_change_notifies_only_when_status_changes(): void
    {
        $application = Application::factory()->for($this->candidate)->create(['status' => 'applied']);

        $this->putJson("/api/applications/{$application->id}", ['notes' => 'Just a note'])->assertOk();
        $this->assertSame(0, $this->candidate->notifications()->count());

        $this->putJson("/api/applications/{$application->id}", ['status' => 'offer'])->assertOk();

        $notification = $this->candidate->notifications()->first();
        $this->assertSame('application_status_changed', $notification->data['kind']);
        $this->assertSame('Offer received', $notification->data['title']);
        $this->assertStringContainsString('from Applied to Offer', $notification->data['message']);
    }

    public function test_closing_a_job_notifies_candidates_still_in_process(): void
    {
        $job = Job::factory()->create();
        $active = Application::factory()->for($job)->create(['status' => 'interview']);
        $rejected = Application::factory()->for($job)->create(['status' => 'rejected']);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->putJson("/api/admin/jobs/{$job->id}", ['status' => 'closed'])->assertOk();

        $this->assertSame('job_closed', $active->user->notifications()->first()?->data['kind']);
        $this->assertSame(0, $rejected->user->notifications()->count());
    }

    public function test_completing_an_interview_notifies_the_candidate(): void
    {
        $this->fakeAi(fn ($system, $prompt, $schema) => array_key_exists('technical', $schema['properties'])
            ? ['technical' => 7, 'communication' => 7, 'relevance' => 7, 'summary' => 's', 'strengths' => [], 'areas_to_improve' => [], 'recommended_practice' => [], 'recommendation' => 'r']
            : ['score' => 7, 'feedback' => 'f', 'strengths' => [], 'weaknesses' => [], 'improved_answer' => 'a']);
        $interview = Interview::factory()->for($this->candidate)->started(1)->create();
        $question = $interview->questions()->first();

        $this->postJson("/api/interviews/{$interview->id}/questions/{$question->id}/answer", ['answer' => 'My answer'])->assertOk();
        $this->postJson("/api/interviews/{$interview->id}/complete")->assertOk();

        $data = $this->candidate->notifications()->first()->data;
        $this->assertSame('interview_completed', $data['kind']);
        $this->assertStringContainsString('70/100', $data['message']);
    }

    public function test_mark_read_and_read_all(): void
    {
        $jobs = Job::factory()->count(2)->create();
        foreach ($jobs as $job) {
            $this->postJson('/api/applications', ['job_id' => $job->id])->assertCreated();
        }
        $id = $this->candidate->notifications()->first()->id;

        $this->postJson("/api/notifications/{$id}/read")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/notifications?unread=1')->assertJsonPath('data.unread_count', 1)->assertJsonCount(1, 'data.items');

        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 0);
    }

    public function test_users_cannot_read_or_mark_others_notifications(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->postJson('/api/applications', ['job_id' => Job::factory()->create()->id])->assertCreated();
        $foreignId = $other->notifications()->first()->id;

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');
        $this->postJson("/api/notifications/{$foreignId}/read")->assertNotFound();
        $this->assertNull($other->notifications()->first()->read_at);
    }

    public function test_guests_cannot_list_notifications(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/notifications')->assertUnauthorized();
    }
}
