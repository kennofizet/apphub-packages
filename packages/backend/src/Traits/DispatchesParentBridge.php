<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeArgValidator;

trait DispatchesParentBridge
{
    protected function normalizeHubBridgeQuery(?array $raw): array
    {
        $validator = app(ParentBridgeArgValidator::class);
        $validated = $validator->validateArgs(
            ['query' => ['type' => 'pagination_search', 'optional' => true, 'max_per_page' => 100]],
            ['query' => is_array($raw) ? $raw : []],
        );

        return is_array($validated['query'] ?? null) ? $validated['query'] : ['page' => 1, 'per_page' => 20];
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, int|null>}
     */
    protected function hubBridgePaginationResponse(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
