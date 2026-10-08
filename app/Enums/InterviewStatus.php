<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
