<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Normalizes legacy bare block field names inside the declarative projection. */
final class NormalizeListingProjectionReferences extends Migration
{
    public function up(): void
    {
        $blockIds = $this->blockIds(['collection_grid', 'collection_listing']);
        if ($blockIds === []) {
            return;
        }

        $instances = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_config, c.block_template')
            ->join('cms_collections c', "c.id = JSON_UNQUOTE(JSON_EXTRACT(i.block_config, '$.collection_id'))", 'left', false)
            ->whereIn('i.block_id', $blockIds)
            ->get()->getResultArray();

        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config) || ! is_array($config['listing_projection'] ?? null)) {
                continue;
            }
            $template = json_decode((string) ($instance['block_template'] ?? '{}'), true);
            $blockKey = $this->primaryBlockKey($template);
            $projection = $config['listing_projection'];
            $projection['slots'] = is_array($projection['slots'] ?? null) ? $projection['slots'] : [];
            $projection['order'] = is_array($projection['order'] ?? null) ? $projection['order'] : [];
            foreach (['date'] as $slot) {
                $projection['slots'][$slot] = $this->normalize((string) ($projection['slots'][$slot] ?? ''), $blockKey);
            }
            $projection['order']['field'] = $this->normalize((string) ($projection['order']['field'] ?? ''), $blockKey);
            $config['listing_projection'] = $projection;
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
    }

    /** @param list<string> $keys @return list<int> */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->select('id')->whereIn('block_key', $keys)->get()->getResultArray();
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    private function primaryBlockKey(mixed $template): string
    {
        $blocks = is_array($template) && is_array($template['blocks'] ?? null) ? $template['blocks'] : [];
        foreach ($blocks as $block) {
            if (is_array($block) && trim((string) ($block['block_key'] ?? '')) !== '') {
                return trim((string) $block['block_key']);
            }
        }
        return '';
    }

    private function normalize(string $source, string $blockKey): string
    {
        if ($source === '' || $blockKey === '' || str_contains($source, '.')) {
            return $source;
        }
        return 'block.' . $blockKey . '.' . $source;
    }
}
