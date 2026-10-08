<?php

namespace Database\Factories;

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_id' => Job::factory(),
            'cv_id' => null,
            'type' => InterviewType::Mixed,
            'status' => InterviewStatus::Pending,
        ];
    }

    /** In progress with `$count` questions (hr/technical/behavioral rotating). */
    public function started(int $count = 3): static
    {
        return $this->state(fn () => ['status' => InterviewStatus::InProgress, 'started_at' => now()])
            ->afterCreating(function (Interview $interview) use ($count) {
                $categories = ['hr', 'technical', 'behavioral'];
                for ($i = 1; $i <= $count; $i++) {
                    $interview->questions()->create([
                        'question' => "Practice question {$i}?",
                        'category' => $categories[($i - 1) % 3],
                        'order' => $i,
                    ]);
                }
            });
    }
}
