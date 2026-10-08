<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;

class ApplicationStatusChanged extends HireFlowNotification
{
    public function __construct(private Application $application, private ApplicationStatus $from) {}

    public function toArray(object $notifiable): array
    {
        $to = $this->application->status;

        return [
            'kind' => 'application_status_changed',
            'title' => match ($to) {
                ApplicationStatus::Offer => 'Offer received',
                ApplicationStatus::Hired => 'Congratulations — you\'re hired',
                ApplicationStatus::Rejected => 'Application closed',
                ApplicationStatus::Interview, ApplicationStatus::TechnicalInterview => 'Interview stage',
                default => 'Application updated',
            },
            'message' => "{$this->application->job->title}: moved from {$this->from->label()} to {$to->label()}.",
            'subject' => ['type' => 'application', 'id' => $this->application->id],
        ];
    }
}
