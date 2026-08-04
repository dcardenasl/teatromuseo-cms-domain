<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Restores the visible legacy Historia banner (sn_slider id 484).
 *
 * This is an explicit recovery seeder because the image belongs to the Hub
 * and is only available after the legacy migration has uploaded file 1728.
 * It is safe to run repeatedly and never replaces an existing slide.
 */
final class LegacyHistoryHeroSliderSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $pageId = $this->pageIdBySlug('historia');
        $heroBlockId = $this->blockId('hero_slider');
        $slideBlockId = $this->blockId('slide_banner');

        if ($pageId === null || $heroBlockId === null || $slideBlockId === null) {
            throw new \RuntimeException('Historia page or hero slider block types are missing.');
        }

        $heroInstanceId = $this->heroInstanceId($pageId, $heroBlockId);
        if ($heroInstanceId === null) {
            throw new \RuntimeException('Historia page has no hero_slider container. Run SiteBootstrapSeeder first.');
        }

        $this->restoreSlide($pageId, $heroInstanceId, $slideBlockId, 74, 1727, 100, '/uploads/2026/08/01/8e9ea9aa4627-9502bd4dbc636f3f.jpg');
        $this->restoreSlide($pageId, $heroInstanceId, $slideBlockId, 484, 1728, 101, '/uploads/2026/08/01/22d8a5b7104d-23f39a2cc0b656c2.png');

        $this->removeDuplicateHistoryText($pageId);

        echo "LegacyHistoryHeroSliderSeeder: two Historia banners restored from sn_slider 74 and 484.\n";
    }

    private function restoreSlide(int $pageId, int $heroInstanceId, int $slideBlockId, int $legacyId, int $fileId, int $sortOrder, string $url): void
    {
        $instanceId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $slideBlockId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => $heroInstanceId,
            'sort_order' => $sortOrder,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'image' => ['source_kind' => 'hub_file', 'file_id' => $fileId, 'url' => $url],
                'navigation_mode' => 'none',
                'text_color' => '#ffffff',
                'overlay_color' => 'rgba(15, 23, 42, 0.4)',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ($instanceId === null) {
            throw new \RuntimeException("Unable to restore legacy Historia slide {$legacyId}.");
        }

        $translations = ['es' => 'Historia', 'en' => 'History', 'fr' => 'Histoire', 'pt' => 'Nossa História'];
        foreach ($this->languageIds() as $languageCode => $languageId) {
            $this->upsertRecord('cms_block_instance_translations', [
                'instance_id' => $instanceId,
                'language_id' => $languageId,
            ], [
                'block_data' => json_encode([
                    'heading' => $translations[$languageCode] ?? $translations['es'],
                    'subtitle' => null,
                    'cta_label' => null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => 1,
            ]);
        }
    }

    private function pageIdBySlug(string $slug): ?int
    {
        $row = $this->db->table('cms_page_translations')
            ->select('page_id')
            ->where('slug', $slug)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['page_id'];
    }

    private function blockId(string $blockKey): ?int
    {
        $row = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', $blockKey)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function heroInstanceId(int $pageId, int $heroBlockId): ?int
    {
        $row = $this->db->table('cms_block_instances')
            ->select('id')
            ->where([
                'block_id' => $heroBlockId,
                'owner_type' => 'page',
                'owner_id' => $pageId,
                'parent_instance_id' => null,
            ])
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function removeDuplicateHistoryText(int $pageId): void
    {
        $richTextId = $this->blockId('rich_text');
        if ($richTextId === null) {
            return;
        }

        $instances = $this->db->table('cms_block_instances')
            ->select('id, sort_order')
            ->where([
                'block_id' => $richTextId,
                'owner_type' => 'page',
                'owner_id' => $pageId,
                'parent_instance_id' => null,
            ])
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        if (count($instances) < 2) {
            return;
        }

        $canonicalId = null;
        foreach ($instances as $instance) {
            if ((int) $instance['sort_order'] === 3) {
                $canonicalId = (int) $instance['id'];
                break;
            }
        }
        $canonicalId ??= (int) $instances[0]['id'];
        $canonicalData = $this->translationData($canonicalId);

        foreach ($instances as $instance) {
            $instanceId = (int) $instance['id'];
            if ($instanceId === $canonicalId || $this->translationData($instanceId) !== $canonicalData) {
                continue;
            }

            $this->db->table('cms_block_instance_translations')->where('instance_id', $instanceId)->delete();
            $this->db->table('cms_block_instances')->where('id', $instanceId)->delete();
        }
    }

    /** @return array<string, string> */
    private function translationData(int $instanceId): array
    {
        $rows = $this->db->table('cms_block_instance_translations t')
            ->select('l.code, t.block_data')
            ->join('cms_languages l', 'l.id = t.language_id')
            ->where('t.instance_id', $instanceId)
            ->get()
            ->getResultArray();

        $data = [];
        foreach ($rows as $row) {
            $data[(string) $row['code']] = (string) $row['block_data'];
        }
        ksort($data);

        return $data;
    }

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', ['es', 'en', 'fr', 'pt'])
            ->get()
            ->getResultArray();

        $languages = [];
        foreach ($rows as $row) {
            $languages[(string) $row['code']] = (int) $row['id'];
        }

        return $languages;
    }
}
