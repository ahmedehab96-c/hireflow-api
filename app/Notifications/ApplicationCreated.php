<?php

namespace App\Notifications;

use App\Models\Application;

class ApplicationCreated extends HireFlowNotification
{
    public function __construct(private Application $application) {}

    public function toArray(object $notifiable): array
    {
        $job = $this->application->job;
        $company = $job->company?->name;

        return [
            'kind' => 'application_created',
            'title' => $this->application->status->isSubmitted() ? 'Application tracked' : 'Job saved',
            'message' => "{$job->title}".($company ? " at {$company}" : '').' was added to your applications as '.$this->application->status->label().'.',
            'subject' => ['type' => 'application', 'id' => $this->application->id],
        ];
    }
}
