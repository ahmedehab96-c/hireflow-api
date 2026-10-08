<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;

/** A few tracked applications so candidate dashboards aren't empty. */
class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $plan = [
            'candidate1@hireflow.test' => [
                ['Senior Laravel Developer', ApplicationStatus::TechnicalInterview, 'Take-home task submitted; panel next week.'],
                ['Flutter Mobile Engineer', ApplicationStatus::Applied, 'Applied through the company careers page.'],
                ['Frontend Engineer (React)', ApplicationStatus::Saved, null],
            ],
            'candidate2@hireflow.test' => [
                ['Data Analyst', ApplicationStatus::Interview, 'HR screening went well.'],
                ['Digital Marketing Specialist', ApplicationStatus::Rejected, 'Position filled internally.'],
            ],
        ];

        foreach ($plan as $email => $rows) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($rows as [$title, $status, $notes]) {
                $job = Job::where('title', $title)->firstOrFail();

                $application = Application::firstOrNew(['user_id' => $user->id, 'job_id' => $job->id]);
                $application->user_id = $user->id;
                $application->fill([
                    'status' => $status,
                    'applied_at' => $status->isSubmitted() ? now()->subDays(10) : null,
                    'interview_at' => in_array($status, [ApplicationStatus::Interview, ApplicationStatus::TechnicalInterview], true)
                        ? now()->addDays(5)->setTime(10, 0)
                        : null,
                    'notes' => $notes,
                ])->save();
            }
        }
    }
}
