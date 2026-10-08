<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Every API response uses the same envelope:
 * success: {success: true, message, data} / error: {success: false, message, errors}.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'OK',
            'data' => $data ?? (object) [],
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $errors */
    public static function error(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }
}
