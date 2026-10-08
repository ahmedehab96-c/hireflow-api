<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_only_active_jobs(): void
    {
        Job::factory()->count(3)->create();
        Job::factory()->closed()->create();
        Job::factory()->draft()->create();

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonStructure(['data' => ['items' => [['id', 'title', 'status', 'currency', 'company' => ['id', 'name'], 'skills']], 'meta']]);
    }

    public function test_jobs_can_be_filtered(): void
    {
        $laravel = Skill::factory()->create(['name' => 'Laravel']);
        $match = Job::factory()->create(['title' => 'Senior Laravel Developer', 'employment_type' => 'full_time']);
        $match->skills()->attach($laravel);
        Job::factory()->create(['title' => 'Accountant', 'description' => 'Bookkeeping', 'employment_type' => 'part_time']);

        $this->getJson('/api/jobs?search=Laravel')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $match->id);
        $this->getJson('/api/jobs?skill=Laravel')->assertJsonCount(1, 'data.items');
        $this->getJson('/api/jobs?employment_type=part_time')->assertJsonCount(1, 'data.items');
        $this->getJson('/api/jobs?employment_type=invalid')->assertUnprocessable()->assertJsonValidationErrors(['employment_type']);
    }

    public function test_guest_can_view_active_job_details(): void
    {
        $job = Job::factory()->create();
        $job->skills()->attach(Skill::factory()->count(2)->create());

        $this->getJson("/api/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.company.id', $job->company_id)
            ->assertJsonCount(2, 'data.skills');
    }

    public function test_inactive_or_missing_jobs_are_not_found(): void
    {
        $closed = Job::factory()->closed()->create();

        $this->getJson("/api/jobs/{$closed->id}")->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/api/jobs/999999')->assertNotFound();
    }
}
