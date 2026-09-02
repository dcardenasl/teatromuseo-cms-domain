<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\GenerateSwagger;
use CodeIgniter\Test\CIUnitTestCase;
use OpenApi\Generator;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Exercises the exact same OpenAPI\Generator scan that `php spark
 * swagger:generate` performs (see GenerateSwagger::run()), without touching
 * the git-tracked public/swagger.json file.
 *
 * This is also what drives real coverage of every `App\Documentation\*`
 * class and the `#[OA\...]` attributes attached to controllers/DTOs: those
 * attributes are compiled but never evaluated unless something reflects on
 * them, which only happens through this generator (in production, via
 * `swagger:generate`/`swagger-validate`).
 *
 * @internal
 */
#[CoversClass(GenerateSwagger::class)]
final class GenerateSwaggerTest extends CIUnitTestCase
{
    public function testGeneratorScansConfiguredDirectoriesAndProducesDocumentation(): void
    {
        $appPath = APPPATH;

        $openapi = (new Generator())->generate([
            $appPath . 'Config/OpenApi.php',
            $appPath . 'Controllers/',
            $appPath . 'Documentation/',
            $appPath . 'DTO/',
        ]);

        $this->assertNotNull($openapi, 'OpenAPI generator returned no result — no annotations found?');

        $json = $openapi->toJson();
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('paths', $decoded);
        $this->assertNotEmpty($decoded['paths'], 'Expected at least one documented endpoint path.');
        $this->assertArrayHasKey('components', $decoded);
        $this->assertNotEmpty($decoded['components']['schemas'] ?? [], 'Expected at least one documented schema.');

        // A handful of endpoints this domain must always document — regressions
        // here mean a controller/DTO lost its OpenAPI attributes silently.
        $this->assertArrayHasKey('/api/v1/cms/tags', $decoded['paths']);
        $this->assertArrayHasKey('/api/v1/cms/pages', $decoded['paths']);
        $this->assertArrayHasKey('get', $decoded['paths']['/api/v1/cms/tags']);
    }

    public function testCommandIsRegisteredWithExpectedMetadata(): void
    {
        $command = new GenerateSwagger(service('logger'), service('commands'));

        $reflection = new \ReflectionClass($command);

        $group = $reflection->getProperty('group');
        $group->setAccessible(true);
        $this->assertSame('API', $group->getValue($command));

        $name = $reflection->getProperty('name');
        $name->setAccessible(true);
        $this->assertSame('swagger:generate', $name->getValue($command));
    }

    /**
     * Runs the exact same code path as `php spark swagger:generate`. The
     * generated document is deterministic from the source annotations, so
     * this rewrites public/swagger.json with byte-identical content to what
     * is already git-tracked (verified separately by `swagger-validate`) —
     * safe to run from a test.
     */
    public function testRunGeneratesDocumentationAndReturnsSuccess(): void
    {
        $command = new GenerateSwagger(service('logger'), service('commands'));

        $originalContents = file_get_contents(FCPATH . 'swagger.json');
        $this->assertIsString($originalContents);

        $result = $command->run([]);

        $this->assertSame(EXIT_SUCCESS, $result);
        $regenerated = file_get_contents(FCPATH . 'swagger.json');
        $this->assertJson((string) $regenerated);
        $this->assertJsonStringEqualsJsonString($originalContents, (string) $regenerated);
    }
}
