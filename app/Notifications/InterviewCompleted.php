<?php

namespace App\Notifications;

use App\Models\Interview;

class InterviewCompleted extends HireFlowNotification
{
    public function __construct(private Interview $interview) {}

    public function toArray(object $notifiable): array
    {
        $title = $this->interview->job?->title;

        return [
            'kind' => 'interview_completed',
            'title' => 'Interview feedback ready',
            'message' => ($title ? "Your {$title} practice interview" : 'Your practice interview')
                ." scored {$this->interview->total_score}/100. See your strengths and what to practise next.",
            'subject' => ['type' => 'interview', 'id' => $this->interview->id],
        ];
    }
}
