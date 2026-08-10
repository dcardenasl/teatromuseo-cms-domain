<?php

declare(strict_types=1);

namespace App\Documentation\Common;

use OpenApi\Attributes as OA;

/** Machine-readable minimum envelope shared by CMS PublicRead responses. */
#[OA\Schema(
    schema: 'PublicReadEnvelope',
    type: 'object',
    required: ['version', 'ok', 'data', 'meta', 'source', 'messages'],
    properties: [
        new OA\Property(property: 'version', type: 'integer', example: 1),
        new OA\Property(property: 'ok', type: 'boolean'),
        new OA\Property(property: 'data', nullable: true),
        new OA\Property(property: 'meta', type: 'object'),
        new OA\Property(
            property: 'source',
            type: 'object',
            required: ['domain', 'state', 'stale'],
            properties: [
                new OA\Property(property: 'domain', type: 'string', example: 'cms'),
                new OA\Property(property: 'state', type: 'string', enum: ['fresh', 'stale', 'unavailable']),
                new OA\Property(property: 'stale', type: 'boolean'),
            ],
        ),
        new OA\Property(property: 'messages', type: 'array', items: new OA\Items(type: 'string')),
    ],
)]
final class PublicReadEnvelope
{
}
