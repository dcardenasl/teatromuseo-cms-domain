<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Cms;

use App\Exceptions\BlockTemplateValidationException;
use App\Libraries\Cms\BlockReferenceValidator;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class BlockReferenceValidatorTest extends CIUnitTestCase
{
    public function testNormalizesScalarReferenceWhenCollectionIsUnambiguous(): void
    {
        $validator = new BlockReferenceValidator($this->databaseReturning([
            ['id' => 42, 'collection_key' => 'obras'],
        ]));

        $result = $validator->normalizeBlockData(
            ['work' => '42'],
            ['work' => ['type' => 'entry_reference', 'collection_key' => 'obras']],
        );

        $this->assertSame([
            'entry_id' => 42,
            'collection_key' => 'obras',
        ], $result['work']);
    }

    public function testRejectsReferenceFromTheWrongCollection(): void
    {
        $validator = new BlockReferenceValidator($this->databaseReturning([
            ['id' => 42, 'collection_key' => 'personas'],
        ]));

        $this->expectException(BlockTemplateValidationException::class);
        $validator->normalizeBlockData(
            ['work' => 42],
            ['work' => ['type' => 'entry_reference', 'collection_key' => 'obras']],
        );
    }

    public function testRejectsSelfReference(): void
    {
        $validator = new BlockReferenceValidator($this->databaseReturning([
            ['id' => 42, 'collection_key' => 'obras'],
        ]));

        $this->expectException(BlockTemplateValidationException::class);
        $validator->normalizeBlockData(
            ['related' => 42],
            ['related' => ['type' => 'entry_reference', 'collection_key' => 'obras']],
            42,
        );
    }

    /** @param list<array{id: int, collection_key: string}> $rows */
    private function databaseReturning(array $rows): BaseConnection
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getResultArray')->willReturn($rows);

        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('select')->willReturnSelf();
        $builder->method('join')->willReturnSelf();
        $builder->method('whereIn')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);

        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);

        return $db;
    }
}
