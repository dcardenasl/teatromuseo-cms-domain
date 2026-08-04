<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aligns existing public page and collection translations with the canonical
 * locale-aware routes used by the public web application.
 *
 * This is intentionally forward-only: the previous values were historical
 * aliases, not editorial content, and restoring them would recreate broken
 * menu and language-switcher links.
 *
 * @cms-public-route-data-migration
 */
final class NormalizePublicNavigationSlugs extends Migration
{
    /** @var array<string, array<string, string>> */
    private const PAGE_SLUGS = [
        'events' => [
            'es' => 'cartelera',
            'en' => 'events',
            'fr' => 'programme',
            'pt' => 'eventos',
        ],
        'catalog_listing' => [
            'es' => 'museo/coleccion',
            'en' => 'museum/collection',
            'fr' => 'musee/collection',
            'pt' => 'museu/colecao',
        ],
    ];

    /** @var array<string, string> */
    private const COURSE_SLUGS = [
        'es' => 'teatroescuela',
        'en' => 'theaterschool',
        'fr' => 'theatreecole',
        'pt' => 'escola-de-teatro',
    ];

    public function up(): void
    {
        $this->normalizePageSlugs();
        $this->normalizeCourseSlugs();
    }

    public function down(): void
    {
        // Forward-only. Reintroducing the historical aliases would invalidate
        // the canonical routes and language switcher contract.
    }

    private function normalizePageSlugs(): void
    {
        foreach (self::PAGE_SLUGS as $pageType => $slugs) {
            foreach ($slugs as $languageCode => $slug) {
                $this->db->query(
                    'UPDATE cms_page_translations t '
                    . 'INNER JOIN cms_pages p ON p.id = t.page_id '
                    . 'INNER JOIN cms_languages l ON l.id = t.language_id '
                    . 'SET t.slug = ? '
                    . 'WHERE p.page_type = ? AND l.code = ?',
                    [$slug, $pageType, $languageCode],
                );
            }
        }
    }

    private function normalizeCourseSlugs(): void
    {
        foreach (self::COURSE_SLUGS as $languageCode => $slug) {
            $this->db->query(
                'UPDATE cms_collection_translations t '
                . 'INNER JOIN cms_collections c ON c.id = t.collection_id '
                . 'INNER JOIN cms_languages l ON l.id = t.language_id '
                . 'SET t.slug = ? '
                . 'WHERE c.collection_key = ? AND l.code = ?',
                [$slug, 'cursos', $languageCode],
            );
        }
    }
}
