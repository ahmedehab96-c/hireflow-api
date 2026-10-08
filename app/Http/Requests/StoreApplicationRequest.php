<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Enums\JobStatus;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_id' => [
                'required', 'integer',
                Rule::exists('jobs', 'id')->where('status', JobStatus::Active->value),
                Rule::unique('applications', 'job_id')->where('user_id', $this->user()->id),
            ],
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'applied_at' => ['nullable', 'date'],
            'interview_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'job_id.exists' => 'This job does not exist or is no longer accepting applications.',
            'job_id.unique' => 'You are already tracking this job.',
        ];
    }
}
