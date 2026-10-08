<?php

namespace App\Enums;

enum InterviewType: string
{
    case Hr = 'hr';
    case Technical = 'technical';
    case Behavioral = 'behavioral';
    case Mixed = 'mixed';

    /**
     * How many questions of each category the interview contains (8 in total).
     *
     * @return array<string, int>
     */
    public function questionPlan(): array
    {
        return match ($this) {
            self::Mixed => ['hr' => 3, 'technical' => 3, 'behavioral' => 2],
            default => [$this->value => 8],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Hr => 'HR',
            self::Technical => 'Technical',
            self::Behavioral => 'Behavioral',
            self::Mixed => 'Mixed',
        };
    }
}
