<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Cms;

use App\DTO\Request\Cms\BlockInstanceUpdateRequestDTO;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Services;

/**
 * @internal
 */
final class BlockInstanceServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    public function testUpdatePersistsBlockConfigAsJson(): void
    {
        $db = Database::connect();

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query("DELETE FROM `cms_block_instance_translations`");
        $db->query("DELETE FROM `cms_block_instances`");
        $db->query("DELETE FROM `cms_content_blocks`");
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        $db->table('cms_content_blocks')->insert([
            'id' => 5,
            'block_key' => 'hero',
            'name' => 'Hero',
            'schema_definition' => json_encode(['fields' => []]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $db->table('cms_block_instances')->insert([
            'id' => 10,
            'block_id' => 5,
            'owner_type' => 'page',
            'owner_id' => 21,
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $dto = $this->hydrateDto(BlockInstanceUpdateRequestDTO::class, [
            'block_config' => ['theme' => 'dark'],
        ]);

        $service = Services::blockInstanceService(false);
        $result = $service->update(10, $dto, null);

        $this->assertSame(['theme' => 'dark'], $result->toArray()['block_config']);
        $stored = $db->table('cms_block_instances')->getWhere(['id' => 10])->getRowArray()['block_config'];
        $this->assertSame(['theme' => 'dark'], json_decode((string) $stored, true));
    }

    public function testUpdateDecodesJsonConfigFieldsBeforePersisting(): void
    {
        $db = Database::connect();

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query("DELETE FROM `cms_block_instance_translations`");
        $db->query("DELETE FROM `cms_block_instances`");
        $db->query("DELETE FROM `cms_content_blocks`");
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        $db->table('cms_content_blocks')->insert([
            'id' => 5,
            'block_key' => 'collection_grid',
            'name' => 'Collection grid',
            'schema_definition' => json_encode([
                'fields' => [],
                'config_fields' => [
                    'listing_projection' => ['type' => 'json'],
                ],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 0,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $db->table('cms_block_instances')->insert([
            'id' => 10,
            'block_id' => 5,
            'owner_type' => 'page',
            'owner_id' => 21,
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $projection = [
            'version' => 2,
            'order' => [
                'field' => 'block.teatroescuela_ficha.start_date',
                'direction' => 'upcoming',
            ],
        ];
        $dto = $this->hydrateDto(BlockInstanceUpdateRequestDTO::class, [
            'block_id' => 5,
            'block_config' => [
                'listing_projection' => json_encode($projection, JSON_THROW_ON_ERROR),
            ],
        ]);

        $service = Services::blockInstanceService(false);
        $service->update(10, $dto, null);

        $stored = $db->table('cms_block_instances')->getWhere(['id' => 10])->getRowArray()['block_config'];
        $config = json_decode((string) $stored, true);
        $this->assertIsArray($config['listing_projection'] ?? null);
        $this->assertSame('upcoming', $config['listing_projection']['order']['direction']);
        $this->assertSame('block.teatroescuela_ficha.start_date', $config['listing_projection']['order']['field']);
    }

    public function testIndexDefaultsToSortOrderBySortOrderAsc(): void
    {
        $db = Database::connect();

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query("DELETE FROM `cms_block_instance_translations`");
        $db->query("DELETE FROM `cms_block_instances`");
        $db->query("DELETE FROM `cms_content_blocks`");
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        $db->table('cms_content_blocks')->insert([
            'id' => 5,
            'block_key' => 'hero',
            'name' => 'Hero',
            'schema_definition' => json_encode(['fields' => []]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $db->table('cms_block_instances')->insertBatch([
            [
                'id' => 10,
                'block_id' => 5,
                'owner_type' => 'page',
                'owner_id' => 21,
                'sort_order' => 3,
                'is_active' => 1,
            ],
            [
                'id' => 11,
                'block_id' => 5,
                'owner_type' => 'page',
                'owner_id' => 21,
                'sort_order' => 1,
                'is_active' => 1,
            ],
            [
                'id' => 12,
                'block_id' => 5,
                'owner_type' => 'page',
                'owner_id' => 21,
                'sort_order' => 2,
                'is_active' => 1,
            ],
        ]);

        $service = Services::blockInstanceService(false);
        $service->setOwnerContext('page', 21);

        $dto = $this->hydrateDto(\App\DTO\Request\Cms\BlockInstanceIndexRequestDTO::class, []);

        $result = $service->index($dto, null);
        $items = $result->toArray()['data'];

        $this->assertCount(3, $items);
        $this->assertSame(11, $items[0]->id);
        $this->assertSame(12, $items[1]->id);
        $this->assertSame(10, $items[2]->id);
    }

    /**
     * End-to-end coverage for docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md
     * §2.A's write side: saving an entry-owned block instance through the
     * real service (not a direct DB insert) must materialize
     * cms_entry_facet_values via EntryFacetValueSynchronizer, and a second
     * save with a changed value must replace the old row rather than
     * accumulate stale ones.
     */
    public function testUpdatingAnEntryOwnedBlockMaterializesAndReplacesFacetValues(): void
    {
        $db = Database::connect();

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query('DELETE FROM `cms_entry_facet_values`');
        $db->query('DELETE FROM `cms_block_instance_translations`');
        $db->query('DELETE FROM `cms_block_instances`');
        $db->query('DELETE FROM `cms_content_blocks`');
        $db->query('DELETE FROM `cms_entries`');
        $db->query('DELETE FROM `cms_collections`');
        $db->query('DELETE FROM `cms_languages`');
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        $db->table('cms_languages')->insert([
            'id' => 1,
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_default' => 1,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $db->table('cms_collections')->insert([
            'id' => 1,
            'collection_key' => 'facet-write-collection',
            'collection_type' => 'other',
            'is_active' => 1,
            'requires_approval' => 0,
            'enables_categories' => 0,
            'enables_tags' => 0,
            'sort_order' => 0,
        ]);

        $db->table('cms_entries')->insert([
            'id' => 1,
            'collection_id' => 1,
            'workflow_status' => 'published',
            'is_featured' => 0,
            'view_count' => 0,
            'sort_order' => 0,
            'is_in_sitemap' => 1,
        ]);

        $db->table('cms_content_blocks')->insert([
            'id' => 5,
            'block_key' => 'facet_write_block',
            'name' => 'Facet write block',
            'schema_definition' => json_encode([
                'fields' => ['genre' => ['type' => 'select', 'label' => 'Genre']],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $db->table('cms_block_instances')->insert([
            'id' => 10,
            'block_id' => 5,
            'owner_type' => 'entry',
            'owner_id' => 1,
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $dto = $this->hydrateDto(BlockInstanceUpdateRequestDTO::class, [
            'translations' => [
                ['language_id' => 1, 'block_data' => ['genre' => 'documental'], 'is_published' => true],
            ],
        ]);

        Services::blockInstanceService(false)->update(10, $dto, null);

        $rows = $db->table('cms_entry_facet_values')->where('block_instance_id', 10)->orderBy('field_key', 'ASC')->get()->getResultArray();
        $byFieldKey = [];
        foreach ($rows as $row) {
            $byFieldKey[$row['field_key']] = $row;
        }

        $this->assertArrayHasKey('block.facet_write_block.genre', $byFieldKey);
        $this->assertArrayHasKey('genre', $byFieldKey);
        $this->assertSame('documental', $byFieldKey['block.facet_write_block.genre']['value_string']);
        $this->assertSame('string', $byFieldKey['block.facet_write_block.genre']['value_type']);
        $this->assertSame(1, (int) $byFieldKey['block.facet_write_block.genre']['entry_id']);

        // Second save with a changed value must replace, not accumulate.
        $dtoUpdated = $this->hydrateDto(BlockInstanceUpdateRequestDTO::class, [
            'translations' => [
                ['language_id' => 1, 'block_data' => ['genre' => 'ficcion'], 'is_published' => true],
            ],
        ]);
        Services::blockInstanceService(false)->update(10, $dtoUpdated, null);

        $rowsAfter = $db->table('cms_entry_facet_values')->where('block_instance_id', 10)->get()->getResultArray();
        $this->assertCount(2, $rowsAfter, json_encode($rowsAfter));
        foreach ($rowsAfter as $row) {
            $this->assertSame('ficcion', $row['value_string']);
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @return T
     */
    private function hydrateDto(string $class, array $data): object
    {
        $reflection = new \ReflectionClass($class);
        /** @var object $dto */
        $dto = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('map');
        $method->setAccessible(true);
        $method->invoke($dto, $data);

        return $dto;
    }
}
