<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $min = fake()->numberBetween(8, 30) * 1000;

        return [
            'company_id' => Company::factory(),
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraphs(3, true),
            'location' => fake()->randomElement(['Dubai, UAE', 'Abu Dhabi, UAE', 'Remote']),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'salary_min' => $min,
            'salary_max' => $min + fake()->numberBetween(2, 10) * 1000,
            'currency' => 'AED',
            'status' => JobStatus::Active,
            'application_url' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => JobStatus::Closed]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => JobStatus::Draft]);
    }
}
