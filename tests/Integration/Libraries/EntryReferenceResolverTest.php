<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\Cms\EntryReferenceResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Fixtures\CmsFixtureFactory;

/** @internal */
final class EntryReferenceResolverTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private EntryReferenceResolver $resolver;

    private CmsFixtureFactory $fixtures;

    /** @var list<array{id:int,code:string,name:string,is_default:bool}> */
    private array $languages;

    /** @var array{id:int,key:string,translations:list<array<string,mixed>>} */
    private array $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $db = Database::connect();
        $this->resolver = new EntryReferenceResolver($db);
        $this->seedDatabase();
    }

    private function seedDatabase(): void
    {
        $db = Database::connect();
        $db->disableForeignKeyChecks();
        $db->query('DELETE FROM `cms_entry_translations`');
        $db->query('DELETE FROM `cms_entries`');
        $db->query('DELETE FROM `cms_collection_translations`');
        $db->query('DELETE FROM `cms_collections`');
        $db->query('DELETE FROM `cms_languages`');
        $db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($db, self::class);
        $this->languages = $this->fixtures->languages(3);
        $db->table('cms_languages')
            ->where('id', $this->languages[2]['id'])
            ->update(['is_active' => 0]);

        $collectionTranslations = [];
        foreach ([$this->languages[0], $this->languages[1]] as $language) {
            $collectionTranslations[] = [
                'language_id' => $language['id'],
                'slug' => $this->fixtures->slug('collection-slug', $language['code']),
            ];
        }
        $this->collection = $this->fixtures->collection($collectionTranslations, ['collection_key' => 'obras']);
    }

    public function testResolveReturnsHappyPathShapeForRequestedLanguage(): void
    {
        $entry = $this->fixtures->entry($this->collection['id'], [
            [
                'language_id' => $this->languages[0]['id'],
                'slug' => 'obra-es',
                'title' => 'Obra en español',
                'excerpt' => 'Resumen es',
            ],
            [
                'language_id' => $this->languages[1]['id'],
                'slug' => 'obra-en',
                'title' => 'Work in English',
                'excerpt' => 'Summary en',
            ],
        ]);

        $result = $this->resolver->resolve(
            [['entry_id' => $entry['id'], 'collection_key' => 'obras']],
            $this->languages[1]['code']
        );

        $key = 'obras:' . $entry['id'];
        $this->assertArrayHasKey($key, $result);
        $payload = $result[$key];
        $this->assertNotNull($payload);
        $this->assertSame('Work in English', $payload['title']);
        $this->assertSame('obra-en', $payload['slug']);
        $this->assertSame('Summary en', $payload['excerpt']);
    }

    public function testResolveFallsBackToDefaultLanguageWhenRequestedTranslationIsMissing(): void
    {
        $entry = $this->fixtures->entry($this->collection['id'], [
            [
                'language_id' => $this->languages[0]['id'],
                'slug' => 'solo-default',
                'title' => 'Solo en idioma por defecto',
            ],
        ]);

        $result = $this->resolver->resolve(
            [['entry_id' => $entry['id'], 'collection_key' => 'obras']],
            $this->languages[1]['code']
        );

        $key = 'obras:' . $entry['id'];
        $payload = $result[$key];
        $this->assertNotNull($payload);
        $this->assertSame('Solo en idioma por defecto', $payload['title']);
    }

    public function testResolveFallsBackToDefaultLanguageWhenRequestedLanguageIsInactive(): void
    {
        $entry = $this->fixtures->entry($this->collection['id'], [
            [
                'language_id' => $this->languages[0]['id'],
                'slug' => 'default-slug',
                'title' => 'Título por defecto',
            ],
        ]);

        $result = $this->resolver->resolve(
            [['entry_id' => $entry['id'], 'collection_key' => 'obras']],
            $this->languages[2]['code']
        );

        $key = 'obras:' . $entry['id'];
        $payload = $result[$key];
        $this->assertNotNull($payload);
        $this->assertSame('Título por defecto', $payload['title']);
    }

    public function testResolveExcludesUnpublishedEntries(): void
    {
        $entry = $this->fixtures->entry($this->collection['id'], [
            ['language_id' => $this->languages[0]['id'], 'slug' => 'borrador', 'title' => 'Borrador'],
        ], ['workflow_status' => 'draft']);

        $result = $this->resolver->resolve(
            [['entry_id' => $entry['id'], 'collection_key' => 'obras']],
            $this->languages[0]['code']
        );

        $key = 'obras:' . $entry['id'];
        $this->assertArrayHasKey($key, $result);
        $this->assertNull($result[$key]);
    }

    public function testResolveExcludesEntriesFromInactiveCollections(): void
    {
        $inactiveCollection = $this->fixtures->collection([], [
            'collection_key' => 'inactivos',
            'is_active' => 0,
        ]);
        $entry = $this->fixtures->entry($inactiveCollection['id'], [
            ['language_id' => $this->languages[0]['id'], 'slug' => 'oculto', 'title' => 'Oculto'],
        ]);

        $result = $this->resolver->resolve(
            [['entry_id' => $entry['id'], 'collection_key' => 'inactivos']],
            $this->languages[0]['code']
        );

        $key = 'inactivos:' . $entry['id'];
        $this->assertNull($result[$key]);
    }

    public function testResolveReturnsEmptyArrayForEmptyReferenceList(): void
    {
        $this->assertSame([], $this->resolver->resolve([], $this->languages[0]['code']));
    }
}
