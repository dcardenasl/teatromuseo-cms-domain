<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Makes the legacy TeatroMuseo team available as editorial entries in
 * `personas`. Existing entries are left untouched; missing profiles are added
 * with the two portraits used by the public team hover treatment.
 */
final class CmsTeatroMuseoTeamSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $collection = $this->db->table('cms_collections')
            ->where('collection_key', 'personas')
            ->get()
            ->getRowArray();
        $personaBlock = $this->db->table('cms_content_blocks')
            ->where('block_key', 'persona_ficha')
            ->get()
            ->getRowArray();
        $languages = $this->db->table('cms_languages')->get()->getResultArray();

        if (! $collection || ! $personaBlock || $languages === []) {
            echo "CmsTeatroMuseoTeamSeeder: required CMS records are missing; skipping.\n";
            return;
        }

        $languageIds = [];
        foreach ($languages as $language) {
            $languageIds[(string) $language['code']] = (int) $language['id'];
        }

        foreach ($this->team() as $index => $member) {
            $entryId = $this->findEntry((int) $collection['id'], $member['name']);
            if ($entryId === null) {
                $entryId = $this->createEntry((int) $collection['id'], $member, $index);
            }
            if ($entryId === null) {
                continue;
            }

            $this->ensurePersonaBlock((int) $entryId, (int) $personaBlock['id'], $member, $languageIds);
        }
    }

    /** @return list<array{name: string, slug: string, role: string, primary: string, hover: string}> */
    private function team(): array
    {
        $base = 'https://teatromuseo.cl/images/team/';

        return [
            ['name' => 'Víctor Quiroga', 'slug' => 'victor-quiroga', 'role' => 'Payaso · Presidente fundación', 'primary' => $base . 'victor-quiroga.png', 'hover' => $base . 'victor-quiroga-01.png'],
            ['name' => 'Paulina Beltrán', 'slug' => 'paulina-beltran', 'role' => 'Titiritera · Encargada de proyectos', 'primary' => $base . 'paulina-beltran.png', 'hover' => $base . 'paulina-beltran-01.png'],
            ['name' => 'Constanza Valenzuela', 'slug' => 'constanza-valenzuela', 'role' => 'Diseñadora · Encargada de difusión', 'primary' => $base . 'constanza-valenzuela.png', 'hover' => $base . 'constanza-valenzuela-01.png'],
            ['name' => 'Diego Zúñiga', 'slug' => 'diego-zuniga', 'role' => 'Actor, payaso · Encargado de extensión y ventas', 'primary' => $base . '6713f9c46f0ef.png', 'hover' => $base . '6713f9c476bd5.png'],
            ['name' => 'Claudio Palacios', 'slug' => 'claudio-palacios', 'role' => 'Payaso · Secretario Académico', 'primary' => $base . 'claudio-palacios.png', 'hover' => $base . 'claudio-palacios-01.png'],
            ['name' => 'Felipe Lira', 'slug' => 'felipe-lira', 'role' => 'Bailarín titiritero · Encargado de programación', 'primary' => $base . 'felipe-lira.png', 'hover' => $base . 'felipe-lira-01.png'],
            ['name' => 'Tomás Arce', 'slug' => 'tomas-arce', 'role' => 'Gestor cultural · Encargado de comunicaciones', 'primary' => $base . '6713faca50701.png', 'hover' => $base . '6713faca5094d.png'],
            ['name' => 'Barbara Quiroga', 'slug' => 'barbara-quiroga', 'role' => 'Secretaria · Sala y museo', 'primary' => $base . 'barbara-quiroga.png', 'hover' => $base . 'barbara-quiroga-01.png'],
            ['name' => 'Kevin Zamora', 'slug' => 'kevin-zamora', 'role' => 'Técnico · Jefe técnico', 'primary' => $base . '6713f9d87ce79.png', 'hover' => $base . '6713f9d87d5d7.png'],
            ['name' => 'Javiera Silva', 'slug' => 'javiera-silva', 'role' => 'Periodista · Editora Revista 795', 'primary' => $base . '67128c3937d9c.png', 'hover' => $base . '67128c39380a9.png'],
        ];
    }

    private function findEntry(int $collectionId, string $name): ?int
    {
        $row = $this->db->table('cms_entries e')
            ->select('e.id')
            ->join('cms_entry_translations t', 't.entry_id = e.id')
            ->where('e.collection_id', $collectionId)
            ->where('t.title', $name)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /** @param array{name: string, slug: string, role: string, primary: string, hover: string} $member */
    private function createEntry(int $collectionId, array $member, int $index): ?int
    {
        $entryId = $this->createRecord('cms_entries', [
            'collection_id' => $collectionId,
            'author_id' => null,
            'workflow_status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
            'is_featured' => 0,
            'view_count' => 0,
            'sort_order' => 100 + $index,
            'wizard_extra' => null,
            'is_in_sitemap' => 1,
            'deleted_at' => null,
        ]);
        if ($entryId === null) {
            return null;
        }

        foreach ($this->db->table('cms_languages')->get()->getResultArray() as $language) {
            $code = (string) $language['code'];
            $this->createRecord('cms_entry_translations', [
                'entry_id' => $entryId,
                'language_id' => (int) $language['id'],
                'slug' => $code === 'es' ? $member['slug'] : $member['slug'],
                'title' => $member['name'],
                'excerpt' => $member['role'],
                'featured_file_id' => null,
                'featured_image_url' => $member['primary'],
                'meta_title' => $member['name'] . ' | TeatroMuseo',
                'meta_description' => $member['role'],
                'og_image_file_id' => null,
                'og_type' => 'profile',
                'canonical_url' => null,
                'robots' => 'index, follow',
                'schema_data' => null,
            ]);
        }

        return $entryId;
    }

    /** @param array{name: string, slug: string, role: string, primary: string, hover: string} $member @param array<string, int> $languageIds */
    private function ensurePersonaBlock(int $entryId, int $blockId, array $member, array $languageIds): void
    {
        $instance = $this->db->table('cms_block_instances')
            ->where(['block_id' => $blockId, 'owner_type' => 'entry', 'owner_id' => $entryId])
            ->get()
            ->getRowArray();
        $instanceId = $instance !== null ? (int) $instance['id'] : $this->createRecord('cms_block_instances', [
            'block_id' => $blockId,
            'owner_type' => 'entry',
            'owner_id' => $entryId,
            'parent_instance_id' => null,
            'sort_order' => 1,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => '{}',
        ]);
        if ($instanceId === null) {
            return;
        }

        $data = json_encode([
            'name' => $member['name'],
            'role' => $member['role'],
            'bio' => $member['role'],
            'website' => '',
            'hover_portrait' => [
                'source_kind' => 'external_url',
                'file_id' => null,
                'url' => $member['hover'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($languageIds as $languageId) {
            $this->upsertRecord('cms_block_instance_translations', [
                'instance_id' => $instanceId,
                'language_id' => $languageId,
            ], [
                'block_data' => $data ?: '{}',
                'is_published' => 1,
            ]);
        }
    }
}
