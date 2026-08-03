<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Restores the five visible legacy home banners migrated to hub files 142-146.
 *
 * This is intentionally an explicit recovery seeder, not part of the regular
 * SiteBootstrapSeeder. It only creates missing rows and never deletes or edits
 * an existing homepage block or translation.
 */
final class LegacyHomeHeroSliderSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $homeId = $this->pageId('home');
        $heroId = $this->blockId('hero_slider');
        $slideId = $this->blockId('slide_banner');

        if ($homeId === null || $heroId === null || $slideId === null) {
            throw new \RuntimeException('Home page or hero slider block types are missing.');
        }

        $heroInstanceId = $this->heroInstanceId($homeId, $heroId);
        if ($heroInstanceId === null) {
            throw new \RuntimeException('The homepage has no hero_slider container.');
        }

        $languages = $this->languageIds();
        $slides = [
            [142, 'Visítanos con toda tu comunidad y conoce la magia de Teatromuseo', '/contacto'],
            [143, 'Se abre la Escuela de Nuevos Comediantes 2026', '/cursos'],
            [144, 'Deja la risa volar en Teatromuseo', '/cartelera'],
            [145, 'Puentes escénicos entre Chile y México', '/'],
            [146, 'CELEBRANDO A LOS NIÑOS Y NIÑAS', '/contacto'],
        ];

        foreach ($slides as $index => [$fileId, $heading, $ctaUrl]) {
            $instanceId = $this->upsertRecord('cms_block_instances', [
                'block_id' => $slideId,
                'owner_type' => 'page',
                'owner_id' => $homeId,
                'parent_instance_id' => $heroInstanceId,
                'sort_order' => 101 + $index,
            ], [
                'column_index' => null,
                'is_active' => 1,
                'block_config' => json_encode([
                    'image' => [
                        'source_kind' => 'file',
                        'file_id' => $fileId,
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($instanceId === null) {
                continue;
            }

            foreach ($languages as $languageId) {
                $this->upsertRecord('cms_block_instance_translations', [
                    'instance_id' => $instanceId,
                    'language_id' => $languageId,
                ], [
                    'block_data' => json_encode([
                        'heading' => $heading,
                        'subtitle' => null,
                        'cta_url' => $ctaUrl,
                        'cta_label' => 'Ver más',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_published' => 1,
                ]);
            }
        }

        echo "LegacyHomeHeroSliderSeeder: five home slides restored without overwriting existing content.\n";
    }

    private function pageId(string $pageType): ?int
    {
        $result = $this->db->table('cms_pages')->select('id')->where('page_type', $pageType)->where('deleted_at IS NULL', null, false)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function blockId(string $blockKey): ?int
    {
        $result = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $blockKey)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function heroInstanceId(int $homeId, int $heroBlockId): ?int
    {
        $result = $this->db->table('cms_block_instances')->select('id')
            ->where('block_id', $heroBlockId)
            ->where('owner_type', 'page')
            ->where('owner_id', $homeId)
            ->where('parent_instance_id IS NULL', null, false)
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /** @return list<int> */
    private function languageIds(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            (($result = $this->db->table('cms_languages')->select('id')->whereIn('code', ['es', 'en', 'fr', 'pt'])->orderBy('sort_order', 'ASC')->get()) !== false)
                ? $result->getResultArray()
                : []
        ));
    }
}
