<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cv>
 */
class CvFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => 'cv-'.fake()->unique()->numberBetween(1, 9999).'.pdf',
            'file_path' => 'cvs/test/'.fake()->uuid().'.pdf',
            'extracted_text' => "Software Engineer with 5 years of experience in PHP, Laravel and MySQL.\nBuilt REST APIs and led a team of three developers.",
            'analysis_json' => null,
            'score' => null,
        ];
    }

    public function analyzed(int $score = 72): static
    {
        return $this->state(fn () => [
            'score' => $score,
            'analysis_json' => [
                'score' => $score,
                'summary' => 'Backend engineer focused on PHP.',
                'skills' => ['PHP', 'Laravel', 'MySQL'],
                'experience' => [],
                'education' => [],
                'strengths' => [],
                'weaknesses' => [],
                'missing_keywords' => [],
                'recommendations' => [],
            ],
        ]);
    }
}
