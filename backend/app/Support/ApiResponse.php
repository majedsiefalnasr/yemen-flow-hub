<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ], $status);
    }

    public static function error(string $message, array $errors = [], int $status = 400, ?string $errorCode = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ];

        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        return response()->json($payload, $status);
    }

    public static function unauthorized(string $message = 'Unauthorized action'): JsonResponse
    {
        return self::error($message, [], 401);
    }

    public static function forbidden(string $message = 'Forbidden action', ?string $errorCode = null): JsonResponse
    {
        return self::error($message, [], 403, $errorCode);
    }

    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, [], 404);
    }

    public static function validationError(array $errors): JsonResponse
    {
        return self::error('Validation failed.', $errors, 422);
    }

    public static function stepUpRequired(string $message = 'Fresh MFA verification is required for this action.'): JsonResponse
    {
        return self::forbidden($message, 'STEP_UP_REQUIRED');
    }

    public static function lockedOut(
        string $message = 'Account is temporarily locked due to too many failed attempts.',
        ?int $retryAfter = null
    ): JsonResponse {
        $response = response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'ACCOUNT_LOCKED',
        ], 429);

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) max(1, $retryAfter));
        }

        return $response;
    }
}
