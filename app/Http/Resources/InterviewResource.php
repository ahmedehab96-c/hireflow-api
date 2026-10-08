<?php

namespace App\Http\Resources;

use App\Models\Interview;
use App\Models\InterviewQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Questions are only included once loaded, and only up to the current one:
 * answered questions plus the next unanswered question. Future questions stay hidden
 * until the interview is completed.
 *
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'total_score' => $this->total_score,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'job' => $this->whenLoaded('job', fn () => $this->job ? [
                'id' => $this->job->id,
                'title' => $this->job->title,
                'company' => $this->job->company?->name,
            ] : null),
            'cv' => $this->whenLoaded('cv', fn () => $this->cv ? [
                'id' => $this->cv->id,
                'original_filename' => $this->cv->original_filename,
            ] : null),
            'progress' => [
                'total' => $this->questions_count ?? $this->whenLoaded('questions', fn () => $this->questions->count(), 0),
                'answered' => $this->answers_count ?? $this->whenLoaded('questions', fn () => $this->questions->whereNotNull('answer')->count(), 0),
            ],
            'questions' => $this->whenLoaded('questions', fn () => InterviewQuestionResource::collection($this->visibleQuestions())),
            'feedback' => $this->when($this->isCompleted() && $this->relationLoaded('questions'), fn () => $this->feedback_json),
        ];
    }

    private function visibleQuestions()
    {
        if ($this->isCompleted()) {
            return $this->questions;
        }

        $current = $this->questions->first(fn (InterviewQuestion $q) => $q->answer === null);

        return $this->questions->filter(fn (InterviewQuestion $q) => $q->answer !== null || $q->is($current))->values();
    }
}
