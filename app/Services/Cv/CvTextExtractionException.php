<?php

namespace App\Services\Cv;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The uploaded PDF could not be turned into usable text. Rendered as a 422 with a field error. */
class CvTextExtractionException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 422, ['file' => [$this->getMessage()]]);
    }
}
