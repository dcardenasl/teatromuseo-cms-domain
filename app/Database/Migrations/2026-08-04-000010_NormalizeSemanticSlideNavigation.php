<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Converts slide links from translated URL text to a typed, relational target.
 *
 * @cms-content-data-migration
 */
final class IntroduceSemanticSlideNavigation extends Migration
{
    public function up(): void
    {
        $type = $this->db->table('cms_content_blocks')->where('block_key', 'slide_banner')->get()->getRowArray();
        if (! is_array($type)) {
            return;
        }

        $schema = json_decode((string) ($type['schema_definition'] ?? ''), true);
        if (! is_array($schema)) {
            return;
        }
        $schema['fields']['external_url'] = ['type' => 'url', 'label' => 'URL externa', 'required' => false];
        unset($schema['fields']['cta_url']);
        $schema['config_fields'] = [
            'navigation_mode' => ['type' => 'select', 'label' => 'Destino del slide', 'options' => ['none', 'internal', 'external'], 'default' => 'internal', 'required' => true],
            'navigation_target_type' => ['type' => 'select', 'label' => 'Tipo de destino interno', 'options' => ['page', 'event_listing', 'catalog_listing', 'collection_index'], 'default' => 'event_listing', 'required' => false],
            'page_id' => ['type' => 'select', 'label' => 'Página de destino', 'required' => false],
            'collection_id' => ['type' => 'select', 'label' => 'Colección de destino', 'required' => false],
            'external_target' => ['type' => 'select', 'label' => 'Abrir URL externa', 'options' => ['_self', '_blank'], 'default' => '_self', 'required' => false],
        ] + (array) ($schema['config_fields'] ?? []);
        $schema['navigation'] = ['source' => 'block_config', 'target' => 'slide_destination'];
        $this->db->table('cms_content_blocks')->where('id', (int) $type['id'])->update([
            'schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $instances = $this->db->table('cms_block_instances')->where('block_id', (int) $type['id'])->get()->getResultArray();
        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? ''), true);
            $config = is_array($config) ? $config : [];
            $urls = [];
            foreach ($this->db->table('cms_block_instance_translations')->where('instance_id', (int) $instance['id'])->get()->getResultArray() as $translation) {
                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data)) {
                    continue;
                }
                $url = trim((string) ($data['cta_url'] ?? ''));
                if ($url !== '') {
                    $urls[] = $url;
                }
                if ($this->isAbsoluteExternal($url)) {
                    $data['external_url'] = $url;
                }
                unset($data['cta_url']);
                $this->db->table('cms_block_instance_translations')->where('id', (int) $translation['id'])->update([
                    'block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
            }

            $destination = $this->destinationFor($urls);
            $config['navigation_mode'] = $destination['mode'];
            $config['navigation_target_type'] = $destination['type'];
            if ($destination['page_id'] !== null) {
                $config['page_id'] = $destination['page_id'];
            }
            if ($destination['collection_id'] !== null) {
                $config['collection_id'] = $destination['collection_id'];
            }
            $config['external_target'] = $config['external_target'] ?? '_self';
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only data migration. The semantic model is the source of truth.
    }

    /** @param list<string> $urls @return array{mode:string,type:string,page_id:int|null,collection_id:int|null} */
    private function destinationFor(array $urls): array
    {
        $url = strtolower(trim((string) ($urls[0] ?? '')));
        if ($this->isAbsoluteExternal($url)) {
            return ['mode' => 'external', 'type' => 'page', 'page_id' => null, 'collection_id' => null];
        }
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? $url), '/');
        $segments = explode('/', $path);
        $lastSegment = (string) ($segments[count($segments) - 1] ?? '');
        if (in_array($lastSegment, ['cartelera', 'programming', 'programmation', 'programacao', 'events', 'programme', 'eventos'], true)) {
            return ['mode' => 'internal', 'type' => 'event_listing', 'page_id' => null, 'collection_id' => null];
        }
        $page = $this->db->table('cms_pages')->whereIn('page_type', ['home', 'contact'])->where('status', 'published')->where('deleted_at IS NULL', null, false)->get()->getResultArray();
        foreach ($page as $row) {
            if ($row['page_type'] === 'home' && ($path === '' || $path === 'home')) {
                return ['mode' => 'internal', 'type' => 'page', 'page_id' => (int) $row['id'], 'collection_id' => null];
            }
            if ($row['page_type'] === 'contact' && in_array($path, ['contacto', 'contact', 'contato', 'contactez-nous'], true)) {
                return ['mode' => 'internal', 'type' => 'page', 'page_id' => (int) $row['id'], 'collection_id' => null];
            }
        }
        return ['mode' => 'none', 'type' => 'page', 'page_id' => null, 'collection_id' => null];
    }

    private function isAbsoluteExternal(string $url): bool
    {
        return (bool) preg_match('#^https?://[^\s]+$#i', trim($url));
    }
}
