<?php

namespace App\Http\Resources;

use App\Models\InterviewQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InterviewQuestion */
class InterviewQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'category' => $this->category,
            'question' => $this->question,
            'answer' => $this->answer ? new InterviewAnswerResource($this->answer) : null,
        ];
    }
}
