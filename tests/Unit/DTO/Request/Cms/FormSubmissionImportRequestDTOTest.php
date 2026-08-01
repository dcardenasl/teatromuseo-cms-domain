<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Request\Cms;

use App\DTO\Request\Cms\FormSubmissionImportRequestDTO;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 */
final class FormSubmissionImportRequestDTOTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testRulesAllowStatusEnumAndRequireFormKeyAndFormData(): void
    {
        $dto = $this->makeDto();

        $rules = $dto->rules();

        $this->assertArrayHasKey('form_key', $rules);
        $this->assertArrayHasKey('form_data', $rules);
        $this->assertStringContainsString('in_list[new,read,replied,spam,archived]', $rules['status']);
    }

    public function testMapPreservesHistoricalCreatedAtAndStatus(): void
    {
        $dto = $this->makeDto();

        $this->invokeMap($dto, [
            'form_key'   => 'contact',
            'form_data'  => ['name' => 'Silvana Vargas', 'email' => 'svargas@example.cl', 'message' => 'Hola'],
            'status'     => 'replied',
            'created_at' => '2024-07-11 16:54:23',
            'ip_address' => null,
            'user_agent' => null,
        ]);

        $this->assertSame('replied', $dto->status);
        $this->assertSame('2024-07-11 16:54:23', $dto->created_at);
        $this->assertSame('Silvana Vargas', $dto->form_data['name']);
    }

    public function testMapDefaultsStatusAndCreatedAtWhenAbsent(): void
    {
        $dto = $this->makeDto();

        $this->invokeMap($dto, [
            'form_key'  => 'unknown-form-key',
            'form_data' => ['message' => 'hola'],
        ]);

        $this->assertSame('new', $dto->status);
        $this->assertNull($dto->created_at);
        $this->assertNull($dto->form_id);
    }

    private function makeDto(): FormSubmissionImportRequestDTO
    {
        $reflection = new ReflectionClass(FormSubmissionImportRequestDTO::class);

        /** @var FormSubmissionImportRequestDTO $dto */
        $dto = $reflection->newInstanceWithoutConstructor();

        return $dto;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function invokeMap(object $dto, array $data): void
    {
        $method = new ReflectionMethod($dto, 'map');
        $method->setAccessible(true);
        $method->invoke($dto, $data);
    }
}
