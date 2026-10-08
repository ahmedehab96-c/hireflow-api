<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Authorization is handled by route middleware and policies. */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
