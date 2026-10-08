<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

/** Store (POST) requires the name; updates may send any subset of fields. */
class CompanyRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'url', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
