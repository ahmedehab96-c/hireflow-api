<?php

namespace App\Notifications;

use App\Models\Application;

/** Sent to candidates tracking a job when it stops accepting applications. */
class JobClosed extends HireFlowNotification
{
    public function __construct(private Application $application) {}

    public function toArray(object $notifiable): array
    {
        $job = $this->application->job;
        $company = $job->company?->name;

        return [
            'kind' => 'job_closed',
            'title' => 'Job no longer open',
            'message' => "{$job->title}".($company ? " at {$company}" : '').' has stopped accepting new applications. Your application stays in your tracker.',
            'subject' => ['type' => 'application', 'id' => $this->application->id],
        ];
    }
}
