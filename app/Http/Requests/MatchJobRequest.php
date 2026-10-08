<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class MatchJobRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Scoped to the current user: another candidate's CV id fails exactly like a missing one.
            'cv_id' => ['required', 'integer', Rule::exists('cvs', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cv_id.required' => 'Select one of your CVs.',
            'cv_id.exists' => 'Select one of your CVs.',
        ];
    }
}
