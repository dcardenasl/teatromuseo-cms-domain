<?php

declare(strict_types=1);

namespace App\Documentation\Cms;

use OpenApi\Attributes as OA;

/** OpenAPI contract for the versioned CMS page PublicRead surface. */
final class PublicReadEndpoints
{
    #[OA\Get(
        path: '/api/v1/public-read/{locale}/pages',
        tags: ['Public Read - CMS'],
        summary: 'List published pages in sitemap order',
        security: [['appKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'fields', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PublicRead envelope',
                content: new OA\JsonContent(ref: '#/components/schemas/PublicReadEnvelope'),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
            new OA\Response(response: 422, description: 'Invalid query'),
        ]
    )]
    public function index(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/public-read/{locale}/pages/{path}',
        tags: ['Public Read - CMS'],
        summary: 'Get one published page by hierarchical path',
        security: [['appKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'path', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'fields', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PublicRead envelope',
                content: new OA\JsonContent(ref: '#/components/schemas/PublicReadEnvelope'),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
            new OA\Response(response: 404, description: 'Not found or not published'),
        ]
    )]
    public function show(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/public-read/{locale}/navigation',
        tags: ['Public Read - CMS'],
        summary: 'Get main, footer and legal navigation trees',
        security: [['appKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PublicRead envelope',
                content: new OA\JsonContent(ref: '#/components/schemas/PublicReadEnvelope'),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
        ]
    )]
    public function navigation(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/public-read/{locale}/settings',
        tags: ['Public Read - CMS'],
        summary: 'Get public settings and media metadata',
        security: [['appKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PublicRead envelope',
                content: new OA\JsonContent(ref: '#/components/schemas/PublicReadEnvelope'),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
        ]
    )]
    public function settings(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/public-read/{locale}/entries/{collection}',
        tags: ['Public Read - CMS'],
        summary: 'List published entries from a collection',
        security: [['appKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'collection', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tag', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'order_by', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'order_direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc', 'ASC', 'DESC'])),
            new OA\Parameter(name: 'filter_by', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_value', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_operator', in: 'query', schema: new OA\Schema(type: 'string', enum: ['equals', 'contains'])),
            new OA\Parameter(name: 'include', in: 'query', schema: new OA\Schema(type: 'string', enum: ['listing_content'])),
            new OA\Parameter(name: 'fields', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PublicRead envelope',
                content: new OA\JsonContent(ref: '#/components/schemas/PublicReadEnvelope'),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
            new OA\Response(response: 422, description: 'Invalid query'),
        ]
    )]
    public function entries(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/public-read/{locale}/entries/{collection}/{slug}',
        tags: ['Public Read - CMS'],
        summary: 'Get one published entry by collection and slug',
        security: [['appKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'collection', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'fields', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PublicRead envelope',
                content: new OA\JsonContent(ref: '#/components/schemas/PublicReadEnvelope'),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid X-App-Key'),
            new OA\Response(response: 404, description: 'Not found or not published'),
        ]
    )]
    public function entry(): void
    {
    }
}
