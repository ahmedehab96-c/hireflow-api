<?php

namespace App\Services\AI\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** An AI request failed. The message is safe to show to users; details are logged server-side. */
class AiException extends RuntimeException
{
    public function __construct(string $message = 'The AI service could not complete this request. Please try again in a moment.', protected int $status = 502)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status);
    }
}
