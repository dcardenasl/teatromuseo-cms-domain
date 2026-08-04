<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Backfills the declarative listing projection from the previous date/order settings. */
final class BackfillListingProjections extends Migration
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
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config) || is_array($config['listing_projection'] ?? null)) {
                continue;
            }

            $template = json_decode((string) ($instance['block_template'] ?? '{}'), true);
            $primaryBlockKey = $this->primaryBlockKey($template);
            $dateField = trim((string) ($config['date_field'] ?? ''));
            $orderField = trim((string) ($config['order_by'] ?? ''));
            $dateSource = $this->canonicalSource($dateField, $primaryBlockKey);
            $orderSource = $this->canonicalSource(str_starts_with($orderField, 'field:') ? substr($orderField, 6) : $orderField, $primaryBlockKey);

            $config['listing_projection'] = [
                'version' => 1,
                'slots' => [
                    'title' => 'entry.title',
                    'subtitle' => '',
                    'summary' => 'entry.excerpt',
                    'date' => $dateSource,
                    'image' => '',
                ],
                'extras' => [],
                'order' => [
                    'field' => $orderSource,
                    'direction' => strtolower((string) ($config['order_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
                ],
                'filters' => [],
            ];
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the declarative projection on rollback; legacy keys remain as a fallback.
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

    private function canonicalSource(string $source, string $primaryBlockKey): string
    {
        if ($source === '' || $source === 'auto') {
            return '';
        }
        if (str_starts_with($source, 'listing.') && $primaryBlockKey !== '') {
            return 'block.' . $primaryBlockKey . '.' . substr($source, 8);
        }
        if (in_array($source, ['published_at', 'created_at', 'title'], true)) {
            return 'entry.' . $source;
        }
        return $source;
    }
}
