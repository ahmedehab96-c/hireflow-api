<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class StoreCvRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Extension and detected content type must both be PDF.
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.config('cv.max_file_kb')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a PDF file to upload.',
            'file.mimes' => 'Only PDF files are accepted.',
            'file.mimetypes' => 'Only PDF files are accepted.',
            'file.max' => 'The CV must not be larger than '.(int) (config('cv.max_file_kb') / 1024).' MB.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $limit = config('cv.max_per_user');
                if ($this->user()->cvs()->count() >= $limit) {
                    $validator->errors()->add('file', "You can keep up to {$limit} CVs. Delete an old one to upload a new CV.");
                }
            },
        ];
    }
}
