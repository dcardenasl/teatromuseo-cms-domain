<?php

declare(strict_types=1);

namespace App\Documentation\Health;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/diagnostics/public-read',
    tags: ['Health'],
    summary: 'Public-read capacity diagnostics',
    description: 'Internal application diagnostics. Requires the X-App-Key header and never returns credentials or SQL text.',
    security: [['appKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Diagnostics collected',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'schema', type: 'string', example: 'public-read-diagnostics.v1'),
                    new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'application', type: 'object'),
                    new OA\Property(property: 'database', type: 'object'),
                    new OA\Property(property: 'cache', type: 'object'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
    ]
)]
final class DiagnosticsEndpoints
{
}
