<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use Illuminate\Validation\Rule;

/** Listing filters shared by the public and admin job indexes. */
class JobIndexRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'company_id' => ['nullable', 'integer'],
            'skill' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
