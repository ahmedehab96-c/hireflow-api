<?php

namespace App\Http\Requests\Admin;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Job;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Store (POST) requires the core fields; updates may send any subset. */
class JobRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'company_id' => [$required, 'integer', 'exists:companies,id'],
            'title' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string', 'max:20000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', Rule::enum(EmploymentType::class)],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'alpha'],
            'status' => ['sometimes', 'required', Rule::enum(JobStatus::class)],
            'application_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'skill_ids' => ['sometimes', 'array'],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    /** Salary range is checked against stored values too, so partial updates stay consistent. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $job = $this->route('job');
                $current = $job instanceof Job ? $job : null;

                $min = $this->has('salary_min') ? $this->input('salary_min') : $current?->salary_min;
                $max = $this->has('salary_max') ? $this->input('salary_max') : $current?->salary_max;

                if (is_numeric($min) && is_numeric($max) && (int) $max < (int) $min) {
                    $validator->errors()->add('salary_max', 'The maximum salary must be greater than or equal to the minimum salary.');
                }
            },
        ];
    }
}
