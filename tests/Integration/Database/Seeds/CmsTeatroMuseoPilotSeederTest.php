<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class CmsTeatroMuseoPilotSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->disableForeignKeyChecks();
        foreach ([
            'cms_file_references',
            'cms_block_instance_translations',
            'cms_block_instances',
            'cms_entry_related',
            'cms_entry_versions',
            'cms_entry_translations',
            'cms_entries',
            'cms_content_blocks',
            'cms_collection_translations',
            'cms_collections',
            'cms_languages',
        ] as $table) {
            $this->db->query("DELETE FROM `{$table}`");
        }
        $this->db->enableForeignKeyChecks();
    }

    public function testPilotCreatesTwoEntriesPerCollectionWithReferencesAndRelations(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\CmsLanguageSeeder::class);
        $seeder->call(\App\Database\Seeds\CmsBlockTypeSeeder::class);
        $seeder->call(\App\Database\Seeds\TeatroMuseoBlockTypeSeeder::class);
        $seeder->call(\App\Database\Seeds\CmsTeatroMuseoCollectionSeeder::class);
        $seeder->call(\App\Database\Seeds\CmsTeatroMuseoPilotSeeder::class);

        $this->assertSame(18, $this->db->table('cms_entries')->countAllResults());
        $this->assertSame(36, $this->db->table('cms_entry_translations')->countAllResults());

        $this->assertSame(
            ['es', 'en'],
            array_column(
                $this->db->table('cms_languages')
                    ->select('code')
                    ->where('is_active', 1)
                    ->orderBy('sort_order', 'ASC')
                    ->get()
                    ->getResultArray(),
                'code'
            )
        );

        $collections = $this->db->table('cms_collections')->select('id')->get()->getResultArray();
        $this->assertCount(9, $collections);
        foreach ($collections as $collection) {
            $this->assertSame(2, $this->db->table('cms_entries')
                ->where('collection_id', (int) $collection['id'])
                ->countAllResults());
        }

        $workId = $this->entryIdBySlug('obras-pilot-complete');
        $this->assertNotNull($workId);

        $blocks = $this->db->table('cms_block_instances i')
            ->select('b.block_key')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'entry')
            ->where('i.owner_id', $workId)
            ->orderBy('i.sort_order', 'ASC')
            ->get()
            ->getResultArray();
        $this->assertSame(['obra_ficha', 'related_entries'], array_column($blocks, 'block_key'));

        $obraBlock = $this->db->table('cms_block_instances i')
            ->select('t.block_data')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->join('cms_block_instance_translations t', 't.instance_id = i.id')
            ->join('cms_languages l', 'l.id = t.language_id')
            ->where('i.owner_id', $workId)
            ->where('b.block_key', 'obra_ficha')
            ->where('l.code', 'es')
            ->get()
            ->getRowArray();
        $obraData = json_decode((string) ($obraBlock['block_data'] ?? ''), true);
        $this->assertIsArray($obraData);
        $this->assertSame('companias', $obraData['company']['collection_key'] ?? null);
        $this->assertCount(2, $obraData['people'] ?? []);

        $this->assertSame(2, $this->db->table('cms_entry_related')
            ->where('entry_id', $workId)
            ->countAllResults());

        $before = [
            'entries' => $this->db->table('cms_entries')->countAllResults(),
            'translations' => $this->db->table('cms_entry_translations')->countAllResults(),
            'blocks' => $this->db->table('cms_block_instances')->where('owner_type', 'entry')->countAllResults(),
            'relations' => $this->db->table('cms_entry_related')->countAllResults(),
        ];

        $seeder->call(\App\Database\Seeds\CmsTeatroMuseoPilotSeeder::class);

        $after = [
            'entries' => $this->db->table('cms_entries')->countAllResults(),
            'translations' => $this->db->table('cms_entry_translations')->countAllResults(),
            'blocks' => $this->db->table('cms_block_instances')->where('owner_type', 'entry')->countAllResults(),
            'relations' => $this->db->table('cms_entry_related')->countAllResults(),
        ];
        $this->assertSame($before, $after);
    }

    private function entryIdBySlug(string $slug): ?int
    {
        $row = $this->db->table('cms_entry_translations')
            ->select('entry_id')
            ->where('slug', $slug)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['entry_id'] : null;
    }
}
