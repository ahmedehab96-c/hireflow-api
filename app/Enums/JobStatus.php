<?php

namespace App\Enums;

enum JobStatus: string
{
    case Active = 'active';
    case Draft = 'draft';
    case Closed = 'closed';
}
