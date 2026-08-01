<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\Cms\BlockInstancePurger;
use App\Libraries\Cms\FileReferenceSynchronizer;
use App\Libraries\Cms\FileUrlResolver;
use App\Services\Cms\FileUsageService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Regression coverage for the gap found while cleaning up test data from a
 * legacy-migration dry run (2026-08-01): deleting a CMS entry/page never
 * cleaned up its owned block instances, leaving them behind as orphans that
 * still counted as "in use" for any Hub file they referenced — blocking
 * `DELETE /files/{id}` on the Hub with a 409 for files nothing could reach
 * anymore. See EntryService::afterDelete() / PageService::afterDelete().
 *
 * @internal
 */
final class BlockInstancePurgerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testPurgeForOwnerRemovesInstancesTranslationsAndFreesFileUsage(): void
    {
        $db = Database::connect();

        $db->table('cms_content_blocks')->insert([
            'block_key' => 'purge_test_' . bin2hex(random_bytes(4)),
            'name' => 'Purge test',
            'schema_definition' => json_encode([
                'fields' => [],
                'config_fields' => [
                    'cover' => ['type' => 'media_reference', 'accept' => 'image'],
                ],
            ], JSON_THROW_ON_ERROR),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 0,
        ]);
        $blockId = (int) $db->insertID();

        $db->table('cms_block_instances')->insert([
            'block_id' => $blockId,
            'owner_type' => 'entry',
            'owner_id' => 555,
            'sort_order' => 0,
            'is_active' => 1,
            'block_config' => json_encode(
                ['cover' => ['source_kind' => 'hub_file', 'file_id' => 7001, 'url' => '/files/7001/view']],
                JSON_THROW_ON_ERROR
            ),
        ]);
        $instanceId = (int) $db->insertID();

        $db->table('cms_languages')->insert([
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_default' => 1,
            'is_active' => 1,
        ]);
        $languageId = (int) $db->insertID();

        $db->table('cms_block_instance_translations')->insert([
            'instance_id' => $instanceId,
            'language_id' => $languageId,
            'block_data' => json_encode([], JSON_THROW_ON_ERROR),
            'is_published' => 1,
        ]);

        $resolver = new class () extends FileUrlResolver {
            public function __construct()
            {
            }
        };
        (new FileReferenceSynchronizer($resolver, $db))->syncBlockInstance($instanceId);

        $this->assertSame(
            'block_instances',
            (new FileUsageService($db))->getUsagesByHubFileId(7001)[0]['resource']
        );

        $purged = (new BlockInstancePurger($db))->purgeForOwner('entry', 555);

        $this->assertSame(1, $purged);
        $this->assertSame(
            0,
            $db->table('cms_block_instances')->where('id', $instanceId)->countAllResults()
        );
        $this->assertSame(
            0,
            $db->table('cms_block_instance_translations')->where('instance_id', $instanceId)->countAllResults()
        );
        $this->assertSame([], (new FileUsageService($db))->getUsagesByHubFileId(7001));
    }

    public function testPurgeForOwnerIsANoopWhenOwnerHasNoBlocks(): void
    {
        $purger = new BlockInstancePurger(Database::connect());

        $this->assertSame(0, $purger->purgeForOwner('entry', 999999));
    }
}
