<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Synchronizes the public-facing aspect ratio of collection_grid blocks with
 * the collection they render.
 *
 * This keeps fresh bootstraps and existing content aligned with the same
 * editorial rule set used by the public web view model: square for news,
 * cartelera and works, portrait for courses and people, and the closest
 * available portrait ratio for exhibitions.
 */
final class CmsCollectionGridAspectRatioSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $blockType = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'collection_grid')
            ->get()
            ->getRowArray();

        if (! is_array($blockType) || ! isset($blockType['id'])) {
            echo "CmsCollectionGridAspectRatioSeeder: collection_grid block type not found.\n";

            return;
        }

        $instances = $this->db->table('cms_block_instances')
            ->select('id, block_config')
            ->where('block_id', (int) $blockType['id'])
            ->get()
            ->getResultArray();

        if ($instances === []) {
            return;
        }

        foreach ($instances as $instance) {
            $instanceId = (int) ($instance['id'] ?? 0);
            if ($instanceId <= 0) {
                continue;
            }

            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config)) {
                $config = [];
            }

            $collectionKey = strtolower(trim((string) ($config['collection_key'] ?? '')));
            $desiredRatio = $this->desiredRatioForCollectionKey($collectionKey);
            if ($desiredRatio === null) {
                continue;
            }

            if ((string) ($config['image_aspect_ratio'] ?? '') === $desiredRatio) {
                continue;
            }

            $config['image_aspect_ratio'] = $desiredRatio;
            $this->db->table('cms_block_instances')
                ->where('id', $instanceId)
                ->update([
                    'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
        }
    }

    private function desiredRatioForCollectionKey(string $collectionKey): ?string
    {
        return match ($collectionKey) {
            'cartelera', 'events', 'eventos', 'obras', 'works', 'noticias', 'news', 'publicaciones', 'publications', 'festivales', 'festivals', 'companias', 'companies' => '1/1',
            'cursos', 'courses', 'personas', 'people' => '3/4',
            'exposiciones', 'exhibitions' => '2/3',
            'videos', 'video', 'multimedia' => '16/9',
            default => null,
        };
    }
}
