<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Standard envelope:  { success, message, data, meta }
 */
trait ApiResponses
{
    protected function ok(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }
        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->ok($data, $message, [], 201);
    }

    protected function deleted(string $message = 'Deleted successfully'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => null]);
    }

    /**
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginated(LengthAwarePaginator $paginator, string $resource, string $message = 'OK', array $extraMeta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource::collection($paginator->getCollection())->resolve(request()),
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ], $extraMeta),
        ]);
    }

    protected function perPage(int $default = 20, int $max = 100): int
    {
        return max(1, min($max, (int) request()->integer('per_page', $default)));
    }
}
