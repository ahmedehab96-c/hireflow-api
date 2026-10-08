<?php

namespace App\Http\Resources;

use App\Models\InterviewAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InterviewAnswer */
class InterviewAnswerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'answer' => $this->answer,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'strengths' => $this->strengths_json ?? [],
            'weaknesses' => $this->weaknesses_json ?? [],
            'improved_answer' => $this->improved_answer,
            'answered_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
