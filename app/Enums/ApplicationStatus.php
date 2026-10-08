<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Saved = 'saved';
    case Applied = 'applied';
    case Interview = 'interview';
    case TechnicalInterview = 'technical_interview';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Hired = 'hired';

    /** Every status past "saved" means the candidate has actually applied. */
    public function isSubmitted(): bool
    {
        return $this !== self::Saved;
    }

    public function label(): string
    {
        return match ($this) {
            self::TechnicalInterview => 'Technical interview',
            default => ucfirst($this->value),
        };
    }
}
