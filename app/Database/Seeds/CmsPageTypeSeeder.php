<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Ensures the CMS page type enum supports every page created by bootstrap.
 *
 * This must run before listing-page seeders: MySQL otherwise rejects the
 * `press` and `transparency` rows on a clean install because the original
 * migration predates those page types.
 */
final class CmsPageTypeSeeder extends Seeder
{
    private const PAGE_TYPE_ENUM = "'home','generic','contact','privacy','terms','404','500','maintenance','about','history','events','catalog_listing','collection_index','template_catalog_item','template_event_item','press','publications','transparency'";

    public function run(): void
    {
        if (! $this->db->tableExists('cms_pages')) {
            return;
        }

        $column = $this->db->query("SHOW COLUMNS FROM cms_pages LIKE 'page_type'")->getRowArray();
        $definition = strtolower((string) ($column['Type'] ?? $column['type'] ?? ''));
        foreach (['press', 'publications', 'transparency'] as $requiredType) {
            if (! str_contains($definition, "'{$requiredType}'")) {
                $this->db->query(
                    "ALTER TABLE cms_pages MODIFY page_type ENUM(" . self::PAGE_TYPE_ENUM . ") NOT NULL DEFAULT 'generic'"
                );

                return;
            }
        }
    }
}
