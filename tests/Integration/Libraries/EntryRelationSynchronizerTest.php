<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\Cms\EntryRelationSynchronizer;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Fixtures\CmsFixtureFactory;

/** @internal */
final class EntryRelationSynchronizerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private EntryRelationSynchronizer $synchronizer;

    private CmsFixtureFactory $fixtures;

    private int $ownerId;

    private int $collectionId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = Database::connect();
        $this->synchronizer = new EntryRelationSynchronizer($db);
        $this->seedDatabase($db);
    }

    /** @param \CodeIgniter\Database\BaseConnection<mixed, mixed> $db */
    private function seedDatabase(\CodeIgniter\Database\BaseConnection $db): void
    {
        $db->disableForeignKeyChecks();
        $db->query('DELETE FROM `cms_entry_related`');
        $db->query('DELETE FROM `cms_block_instances`');
        $db->query('DELETE FROM `cms_content_blocks`');
        $db->query('DELETE FROM `cms_entries`');
        $db->query('DELETE FROM `cms_collections`');
        $db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($db, self::class);
        $collection = $this->fixtures->collection([], ['collection_key' => 'obras']);
        $this->collectionId = $collection['id'];
        $this->ownerId = $this->fixtures->entry($this->collectionId)['id'];
    }

    /** `source_block_instance_id` carries a real FK to `cms_block_instances`, so tests need an actual row. */
    private function createBlockInstance(): int
    {
        $db = Database::connect();
        $db->table('cms_content_blocks')->insert([
            'block_key' => 'related_entries_' . bin2hex(random_bytes(4)),
            'name' => 'Related entries',
            'schema_definition' => json_encode(['fields' => []], JSON_THROW_ON_ERROR),
            'supports_pages' => 0,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 0,
        ]);
        $blockId = (int) $db->insertID();

        return $this->fixtures->block($blockId, 'entry', $this->ownerId)['id'];
    }

    /** @return list<int> */
    private function relatedIds(int $ownerId, ?int $sourceBlockInstanceId = null): array
    {
        $db = Database::connect();
        $query = $db->table('cms_entry_related')->select('related_entry_id')->where('entry_id', $ownerId);
        if ($sourceBlockInstanceId !== null) {
            $query->where('source_block_instance_id', $sourceBlockInstanceId);
        }

        $result = $query->orderBy('related_entry_id', 'ASC')->get();
        $rows = $result === false ? [] : $result->getResultArray();

        return array_values(array_map(static fn (array $row): int => (int) $row['related_entry_id'], $rows));
    }

    public function testSyncExcludesSelfReference(): void
    {
        $sourceBlockInstanceId = $this->createBlockInstance();

        $this->synchronizer->sync($this->ownerId, $sourceBlockInstanceId, 'related', [
            ['entry_id' => $this->ownerId, 'collection_key' => 'obras'],
        ]);

        $this->assertSame([], $this->relatedIds($this->ownerId));
    }

    public function testSyncPersistsValidReferences(): void
    {
        $relatedA = $this->fixtures->entry($this->collectionId)['id'];
        $relatedB = $this->fixtures->entry($this->collectionId)['id'];
        $sourceBlockInstanceId = $this->createBlockInstance();

        $this->synchronizer->sync($this->ownerId, $sourceBlockInstanceId, 'related', [
            ['entry_id' => $relatedA, 'collection_key' => 'obras'],
            ['entry_id' => $relatedB, 'collection_key' => 'obras'],
        ]);

        $this->assertSame(
            [min($relatedA, $relatedB), max($relatedA, $relatedB)],
            $this->relatedIds($this->ownerId)
        );
    }

    public function testResyncingWithFewerReferencesRemovesTheOrphanedRelation(): void
    {
        $relatedA = $this->fixtures->entry($this->collectionId)['id'];
        $relatedB = $this->fixtures->entry($this->collectionId)['id'];
        $sourceBlockInstanceId = $this->createBlockInstance();

        $this->synchronizer->sync($this->ownerId, $sourceBlockInstanceId, 'related', [
            ['entry_id' => $relatedA, 'collection_key' => 'obras'],
            ['entry_id' => $relatedB, 'collection_key' => 'obras'],
        ]);
        $this->synchronizer->sync($this->ownerId, $sourceBlockInstanceId, 'related', [
            ['entry_id' => $relatedA, 'collection_key' => 'obras'],
        ]);

        $this->assertSame([$relatedA], $this->relatedIds($this->ownerId));
    }

    public function testSyncScopesReplacementBySourceBlockInstanceOnly(): void
    {
        $relatedFromFirstBlock = $this->fixtures->entry($this->collectionId)['id'];
        $relatedFromSecondBlock = $this->fixtures->entry($this->collectionId)['id'];
        $firstBlockInstanceId = $this->createBlockInstance();
        $secondBlockInstanceId = $this->createBlockInstance();

        $this->synchronizer->sync($this->ownerId, $firstBlockInstanceId, 'related', [
            ['entry_id' => $relatedFromFirstBlock, 'collection_key' => 'obras'],
        ]);
        $this->synchronizer->sync($this->ownerId, $secondBlockInstanceId, 'recommended', [
            ['entry_id' => $relatedFromSecondBlock, 'collection_key' => 'obras'],
        ]);

        $this->assertSame([$relatedFromFirstBlock], $this->relatedIds($this->ownerId, $firstBlockInstanceId));
        $this->assertSame([$relatedFromSecondBlock], $this->relatedIds($this->ownerId, $secondBlockInstanceId));
        $this->assertCount(2, $this->relatedIds($this->ownerId));
    }

    public function testSyncIsANoOpWhenOwnerOrSourceIsInvalid(): void
    {
        $related = $this->fixtures->entry($this->collectionId)['id'];
        $sourceBlockInstanceId = $this->createBlockInstance();

        $this->synchronizer->sync(0, $sourceBlockInstanceId, 'related', [
            ['entry_id' => $related, 'collection_key' => 'obras'],
        ]);
        $this->synchronizer->sync($this->ownerId, 0, 'related', [
            ['entry_id' => $related, 'collection_key' => 'obras'],
        ]);

        $this->assertSame([], $this->relatedIds($this->ownerId));
    }
}
