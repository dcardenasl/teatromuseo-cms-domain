<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use PHPUnit\Framework\TestCase;

/** @internal */
final class PublicReadOpenApiContractTest extends TestCase
{
    public function testPublicReadOperationsDeclareAppKeyAndEnvelopeSchema(): void
    {
        $spec = $this->spec();
        $paths = [
            '/api/v1/public-read/{locale}/pages',
            '/api/v1/public-read/{locale}/pages/{path}',
            '/api/v1/public-read/{locale}/navigation',
            '/api/v1/public-read/{locale}/settings',
            '/api/v1/public-read/{locale}/entries/{collection}',
            '/api/v1/public-read/{locale}/entries/{collection}/{slug}',
        ];

        foreach ($paths as $path) {
            self::assertArrayHasKey($path, $spec['paths'] ?? [], $path . ' must be documented');
            $operation = $spec['paths'][$path]['get'] ?? null;
            self::assertIsArray($operation, $path . ' GET operation is required');
            self::assertContains(['appKeyAuth' => []], $operation['security'] ?? [], $path . ' must require X-App-Key');
            self::assertSame(
                ['$ref' => '#/components/schemas/PublicReadEnvelope'],
                $operation['responses']['200']['content']['application/json']['schema'] ?? null,
                $path . ' must expose the versioned envelope schema',
            );
        }
    }

    public function testEnvelopeSchemaDocumentsTheStableMinimum(): void
    {
        $schema = $this->spec()['components']['schemas']['PublicReadEnvelope'] ?? null;

        self::assertIsArray($schema);
        self::assertSame(
            ['version', 'ok', 'data', 'meta', 'source', 'messages'],
            $schema['required'] ?? null,
        );
        self::assertSame(
            ['domain', 'state', 'stale'],
            $schema['properties']['source']['required'] ?? null,
        );
        self::assertSame(
            ['fresh', 'stale', 'unavailable'],
            $schema['properties']['source']['properties']['state']['enum'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    private function spec(): array
    {
        $json = file_get_contents(ROOTPATH . 'public/swagger.json');
        self::assertNotFalse($json);

        $spec = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($spec);

        return $spec;
    }
}
