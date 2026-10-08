<?php

namespace App\Http\Requests;

class AnswerInterviewQuestionRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answer.required' => 'Write your answer before submitting.',
            'answer.min' => 'Your answer is too short to evaluate.',
            'answer.max' => 'Keep your answer under 5,000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('answer'))) {
            $this->merge(['answer' => trim($this->input('answer'))]);
        }
    }
}
