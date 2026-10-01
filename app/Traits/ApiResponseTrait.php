<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Send a standardized successful JSON response.
     */
    protected function successResponse(mixed $data = null, string $message = 'Operation successful.', int $statusCode = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object)[],
        ];

        return response()->json($payload, $statusCode);
    }

    /**
     * Send a standardized error JSON response.
     */
    protected function errorResponse(string $message = 'An error occurred.', mixed $errors = null, int $statusCode = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object)[],
        ];

        return response()->json($payload, $statusCode);
    }
}
