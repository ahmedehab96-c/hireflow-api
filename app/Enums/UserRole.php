<?php

namespace App\Enums;

enum UserRole: string
{
    case Candidate = 'candidate';
    case Admin = 'admin';
}
