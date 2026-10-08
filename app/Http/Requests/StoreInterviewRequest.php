<?php

namespace App\Http\Requests;

use App\Enums\InterviewType;
use App\Enums\JobStatus;
use Illuminate\Validation\Rule;

/** An interview targets an active job, one of the candidate's CVs, or both. */
class StoreInterviewRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_id' => ['nullable', 'required_without:cv_id', 'integer', Rule::exists('jobs', 'id')->where('status', JobStatus::Active->value)],
            // Scoped to the current user: another candidate's CV fails exactly like a missing one.
            'cv_id' => ['nullable', 'required_without:job_id', 'integer', Rule::exists('cvs', 'id')->where('user_id', $this->user()->id)],
            'type' => ['required', Rule::enum(InterviewType::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'job_id.required_without' => 'Select a job or a CV for this interview.',
            'cv_id.required_without' => 'Select a job or a CV for this interview.',
            'job_id.exists' => 'This job does not exist or is no longer open.',
            'cv_id.exists' => 'Select one of your CVs.',
            'type.enum' => 'Choose HR, technical, behavioral or mixed.',
        ];
    }
}
