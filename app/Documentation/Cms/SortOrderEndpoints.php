<?php

declare(strict_types=1);

namespace App\Documentation\Cms;

use OpenApi\Attributes as OA;

/** OpenAPI definition for the atomic CMS reorder operation. */
final class SortOrderEndpoints
{
    #[OA\Post(
        path: '/api/v1/cms/sort-orders',
        tags: ['Cms'],
        summary: 'Reorder a bounded CMS resource in one operation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CmsSortOrderBatchRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Order saved successfully'),
            new OA\Response(response: 403, description: 'Insufficient permissions'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function reorder(): void
    {
    }
}
