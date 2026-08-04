<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Converts only the roster selected by the About page into editable CMS
 * children. The `personas` collection remains a shared catalogue and is not
 * used as the page's implicit roster.
 */
final class CreateAboutTeamChildren extends Migration
{
    private const PAGE_SLUGS = ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'];

    public function up(): void
    {
        $this->ensureTeamMemberSchema();
        $pageId = $this->pageId();
        $parentId = $pageId !== null ? $this->instanceId($pageId, 'team_grid') : null;
        $teamMemberType = $this->blockTypeId('team_member');
        $collectionId = $this->collectionId('personas');
        $esId = $this->languageId('es');
        if ($parentId === null || $teamMemberType === null || $collectionId === null || $esId === null) {
            return;
        }

        if ($this->db->table('cms_block_instances')->where('parent_instance_id', $parentId)->countAllResults() > 0) {
            return;
        }

        $parent = $this->db->table('cms_block_instances')->select('block_config')->where('id', $parentId)->get()->getRowArray();
        $config = is_array($parent) ? json_decode((string) ($parent['block_config'] ?? '{}'), true) : [];
        $names = array_values(array_filter(array_map('trim', explode(',', (string) ($config['filter_names'] ?? '')))));
        if ($names === []) {
            return;
        }

        $rows = $this->db->table('cms_entries e')
            ->select('e.id, t.title, t.excerpt, t.featured_image_url, e.wizard_extra')
            ->join('cms_entry_translations t', 't.entry_id=e.id AND t.language_id=' . $esId, 'left')
            ->where('e.collection_id', $collectionId)
            ->where('e.workflow_status', 'published')
            ->where('e.deleted_at IS NULL', null, false)
            ->get()->getResultArray();
        $byName = [];
        foreach ($rows as $row) {
            $byName[$this->normalize((string) ($row['title'] ?? ''))] = $row;
        }

        foreach ($names as $order => $name) {
            $entry = $byName[$this->normalize($name)] ?? null;
            if (! is_array($entry)) {
                continue;
            }
            $extra = json_decode((string) ($entry['wizard_extra'] ?? '{}'), true);
            $extra = is_array($extra) ? $extra : [];
            $this->createChild(
                $parentId,
                $teamMemberType,
                $pageId,
                $order + 1,
                (string) ($entry['featured_image_url'] ?? ''),
                $extra,
                (string) ($entry['title'] ?? $name),
                (string) ($entry['excerpt'] ?? '')
            );
        }
    }

    public function down(): void
    {
        // Content migration: editor-created children are never removed automatically.
    }

    private function createChild(int $parentId, int $typeId, ?int $ownerId, int $sort, string $photo, array $extra, string $name, string $excerpt): void
    {
        $hover = $this->hoverUrl($name);
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $typeId,
            'owner_type' => 'page',
            'owner_id' => $ownerId ?? 0,
            'parent_instance_id' => $parentId,
            'sort_order' => $sort,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'photo' => ['source_kind' => 'external_url', 'file_id' => null, 'url' => $photo],
                'hover_photo' => ['source_kind' => 'external_url', 'file_id' => null, 'url' => $hover],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $id = $this->db->insertID();
        if ($id <= 0) {
            return;
        }
        foreach ($this->db->table('cms_languages')->get()->getResultArray() as $language) {
            $code = (string) $language['code'];
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $id,
                'language_id' => (int) $language['id'],
                'block_data' => json_encode([
                    'name' => $name,
                    'position' => (string) ($extra['position'] ?? $excerpt),
                    'profession' => (string) ($extra['profession'] ?? ''),
                    'email' => (string) ($extra['email'] ?? ''),
                    'bio' => $excerpt,
                    'roles' => array_values(array_filter([(string) ($extra['profession'] ?? ''), (string) ($extra['position'] ?? $excerpt)])),
                    'linkedin_url' => '',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => 1,
            ]);
        }
    }

    private function hoverUrl(string $name): string
    {
        $base = 'https://teatromuseo.cl/images/team/';
        $files = [
            'Víctor Quiroga' => 'victor-quiroga-01.png', 'Diego Zuñiga' => '6713f9c476bd5.png',
            'Paulina Beltrán' => 'paulina-beltran-01.png', 'Constanza Valenzuela' => 'constanza-valenzuela-01.png',
            'Felipe Lira' => 'felipe-lira-01.png', 'Claudio Palacios' => 'claudio-palacios-01.png',
            'Kevin Zamora' => '6713f9d87d5d7.png', 'Barbara Quiroga' => 'barbara-quiroga-01.png',
            'Javiera Silva' => '67128c39380a9.png', 'Tomás Arce' => '6713faca5094d.png',
        ];
        return $base . ($files[$name] ?? '');
    }

    private function pageId(): ?int
    {
        $r = $this->db->table('cms_pages p')->select('p.id')->join('cms_page_translations t', 't.page_id=p.id')->whereIn('t.slug', self::PAGE_SLUGS)->where('p.deleted_at IS NULL', null, false)->orderBy('p.id', 'ASC')->get()->getRowArray();
        return is_array($r) ? (int) $r['id'] : null;
    }
    private function instanceId(int $pageId, string $key): ?int
    {
        $r = $this->db->table('cms_block_instances i')->select('i.id')->join('cms_content_blocks b', 'b.id=i.block_id')->where(['i.owner_type' => 'page','i.owner_id' => $pageId,'b.block_key' => $key])->where('i.parent_instance_id IS NULL', null, false)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function blockTypeId(string $key): ?int
    {
        $r = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $key)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function collectionId(string $key): ?int
    {
        $r = $this->db->table('cms_collections')->select('id')->where('collection_key', $key)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function languageId(string $code): ?int
    {
        $r = $this->db->table('cms_languages')->select('id')->where('code', $code)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function normalize(string $value): string
    {
        return mb_strtolower(strtr(trim($value), ['á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u','ñ' => 'n']));
    }

    private function ensureTeamMemberSchema(): void
    {
        $row = $this->db->table('cms_content_blocks')->select('schema_definition')->where('block_key', 'team_member')->get()->getRowArray();
        if (! is_array($row)) {
            return;
        }
        $schema = json_decode((string) ($row['schema_definition'] ?? '{}'), true);
        if (! is_array($schema)) {
            return;
        }
        $schema['fields']['profession'] = ['type' => 'string', 'label' => 'Profesión', 'required' => false];
        $schema['fields']['email'] = ['type' => 'email', 'label' => 'Correo público', 'required' => false];
        $schema['fields']['roles'] = ['type' => 'repeater', 'label' => 'Roles', 'required' => false, 'item_fields' => ['label' => ['type' => 'string', 'label' => 'Rol', 'required' => false]]];
        $schema['config_fields']['hover_photo'] = ['type' => 'media_reference', 'label' => 'Foto al pasar el cursor', 'accept' => 'image', 'required' => false];
        $this->db->table('cms_content_blocks')->where('block_key', 'team_member')->update([
            'schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
