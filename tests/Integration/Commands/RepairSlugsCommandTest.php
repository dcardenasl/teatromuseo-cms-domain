<?php

declare(strict_types=1);

namespace Tests\Integration\Commands;

use App\Commands\RepairSlugs;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Psr\Log\LoggerInterface;
use Tests\Support\Fixtures\CmsFixtureFactory;

/**
 * @internal
 */
final class RepairSlugsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $refresh = true;
    protected $namespace = 'App';

    /** @var list<string>|null */
    private ?array $previousArgv = null;

    private CmsFixtureFactory $fixtures;

    private int $collectionId;

    private int $languageId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousArgv = $_SERVER['argv'] ?? null;
        $this->fixtures = new CmsFixtureFactory($this->db, self::class);

        $language = $this->fixtures->languages(1)[0];
        $collection = $this->fixtures->collection([], ['collection_key' => 'cursos']);

        $this->languageId = (int) $language['id'];
        $this->collectionId = (int) $collection['id'];
    }

    protected function tearDown(): void
    {
        if ($this->previousArgv !== null) {
            $_SERVER['argv'] = $this->previousArgv;
        } else {
            unset($_SERVER['argv']);
        }

        CLI::init();
        parent::tearDown();
    }

    public function testRepairSlugsRewritesCourseEntrySlugsFromTitle(): void
    {
        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'curso_ficha',
            'name' => 'Ficha de curso',
            'schema_definition' => json_encode(['fields' => []], JSON_THROW_ON_ERROR),
        ]);
        $blockTypeId = (int) $this->db->insertID();

        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'sort_order' => 999,
            'is_in_sitemap' => 1,
        ]);
        $entryId = (int) $this->db->insertID();

        $blockInstance = $this->fixtures->block($blockTypeId, 'entry', $entryId);
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $blockInstance['id'],
            'language_id' => $this->languageId,
            'block_data' => json_encode(['start_date' => '2026-07-15'], JSON_THROW_ON_ERROR),
        ]);

        $this->db->table('cms_entry_translations')->insert([
            'entry_id' => $entryId,
            'language_id' => $this->languageId,
            'slug' => 's-ubete-al-escenario-de-prueba-zxq-2026-c40',
            'title' => 'Súbete al escenario de prueba ZXQ',
            'excerpt' => 'Fixture for slug repair.',
        ]);

        $_SERVER['argv'] = ['spark', 'cms:repair-slugs', '--confirm'];
        CLI::init();

        $logger = $this->createMock(LoggerInterface::class);
        $commands = $this->createMock(Commands::class);

        (new RepairSlugs($logger, $commands))->run([]);

        $translation = $this->db->table('cms_entry_translations')
            ->where('entry_id', $entryId)
            ->where('language_id', $this->languageId)
            ->get()
            ->getRowArray();
        self::assertIsArray($translation);
        self::assertSame('subete-al-escenario-de-prueba-zxq-2026', $translation['slug']);
    }
}
