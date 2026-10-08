<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['answer', 'score', 'feedback', 'strengths_json', 'weaknesses_json', 'improved_answer'])]
class InterviewAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'strengths_json' => 'array',
            'weaknesses_json' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class, 'interview_question_id');
    }
}
