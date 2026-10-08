<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Validation\Rule;

/** The job itself can't be changed; delete and re-create instead. */
class UpdateApplicationRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(ApplicationStatus::class)],
            'applied_at' => ['sometimes', 'nullable', 'date'],
            'interview_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
