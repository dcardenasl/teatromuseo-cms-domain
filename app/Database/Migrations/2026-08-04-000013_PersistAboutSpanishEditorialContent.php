<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Persists the approved Spanish institutional copy in the CMS blocks.
 *
 * This content previously came from a public-renderer override. The CMS is
 * now the single source of truth, so the approved copy belongs in its Spanish
 * block translations alongside the other locales.
 *
 * @cms-content-data-migration
 */
final class PersistAboutSpanishEditorialContent extends Migration
{
    public function up(): void
    {
        $pageId = $this->pageId();
        if ($pageId === null) {
            return;
        }

        $richText = $this->instanceId($pageId, 'rich_text');
        if ($richText !== null) {
            $this->mergeTranslation($richText, 'es', [
                'content' => '<h2>Sobre Nosotros</h2><p>Desde el año 2007, la Fundación Teatromuseo del títere y el payaso se ha dedicado a promover, difundir y profesionalizar estas artes de la representación en nuestro país. A través de una escuela de formación nacional e internacional, un museo especializado y una sala de teatro con cartelera familiar permanente.</p><p>Somos un equipo de artistas y profesionales de la gestión cultural que creemos en la vida y la risa como herramientas de desarrollo humano.</p>',
            ]);
        }

        $cardsGrid = $this->instanceId($pageId, 'cards_grid');
        $cardType = $this->blockTypeId('card_item');
        if ($cardsGrid === null || $cardType === null) {
            return;
        }

        $children = $this->db->table('cms_block_instances')
            ->where('parent_instance_id', $cardsGrid)
            ->where('block_id', $cardType)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        if ($children === false) {
            return;
        }

        $copy = [
            ['title' => 'Nuestra Misión', 'description' => 'Fortalecer, difundir y desarrollar el arte del títere y el payaso, enriqueciendo el patrimonio cultural de nuestro país y formando nuevos exponentes mediante redes, escuelas, encuentros, publicaciones y salas de teatro.'],
            ['title' => 'Nuestra Visión', 'description' => 'Consolidar a la Fundación Teatromuseo como un espacio de investigación y desarrollo de estas artes, logrando que Valparaíso sea reconocido nacional e internacionalmente como la capital cultural del títere y el payaso.'],
        ];

        foreach (array_values($children->getResultArray()) as $index => $child) {
            if (! isset($copy[$index])) {
                break;
            }

            $this->mergeTranslation((int) $child['id'], 'es', $copy[$index]);
        }
    }

    public function down(): void
    {
        // Forward-only content migration.
    }

    private function pageId(): ?int
    {
        $result = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations t', 't.page_id = p.id')
            ->whereIn('t.slug', ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('p.id', 'ASC')
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function blockTypeId(string $blockKey): ?int
    {
        $result = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $blockKey)->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function instanceId(int $pageId, string $blockKey): ?int
    {
        $result = $this->db->table('cms_block_instances i')
            ->select('i.id')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'page')
            ->where('i.owner_id', $pageId)
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', $blockKey)
            ->orderBy('i.sort_order', 'ASC')
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /** @param array<string, mixed> $data */
    private function mergeTranslation(int $instanceId, string $language, array $data): void
    {
        $languageResult = $this->db->table('cms_languages')->select('id')->where('code', $language)->get();
        $languageRow = $languageResult === false ? null : $languageResult->getRowArray();
        if (! is_array($languageRow)) {
            return;
        }

        $existingResult = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', $instanceId)
            ->where('language_id', (int) $languageRow['id'])
            ->get();
        $existing = $existingResult === false ? null : $existingResult->getRowArray();
        $current = is_array($existing) ? json_decode((string) ($existing['block_data'] ?? '{}'), true) : [];
        $payload = [
            'block_data' => json_encode(array_merge(is_array($current) ? $current : [], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'is_published' => 1,
        ];

        if (is_array($existing)) {
            $this->db->table('cms_block_instance_translations')->where('id', (int) $existing['id'])->update($payload);
            return;
        }

        $this->db->table('cms_block_instance_translations')->insert($payload + [
            'instance_id' => $instanceId,
            'language_id' => (int) $languageRow['id'],
        ]);
    }
}
