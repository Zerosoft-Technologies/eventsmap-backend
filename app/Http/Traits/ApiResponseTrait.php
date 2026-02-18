<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Return success response with data
     */
    protected function successResponse($data = null, string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return error response
     */
    protected function errorResponse(string $message, int $statusCode = 400, $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Return not found response
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse("{$resource} not found", 404);
    }

    /**
     * Return response with or without data based on existence
     */
    protected function findOrRespond($model, int $id, $resourceClass = null, string $resourceName = 'Resource')
    {
        $item = $model::find($id);

        if (!$item) {
            return $this->notFoundResponse($resourceName);
        }

        $data = $resourceClass ? new $resourceClass($item) : $item;
        
        return $this->successResponse($data);
    }
}
