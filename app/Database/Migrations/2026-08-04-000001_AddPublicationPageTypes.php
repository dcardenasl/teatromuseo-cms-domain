<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives canonical editorial listing pages stable semantic types instead of
 * relying on localized slugs or the generic page type.
 *
 * @cms-content-data-migration
 */
final class AddPublicationPageTypes extends Migration
{
    private const PAGE_TYPES = "'home','generic','contact','privacy','terms','404','500','maintenance','about','history','events','catalog_listing','collection_index','template_catalog_item','template_event_item','press','publications','transparency'";

    public function up(): void
    {
        $this->db->query("ALTER TABLE cms_pages MODIFY page_type ENUM(" . self::PAGE_TYPES . ") NOT NULL DEFAULT 'generic'");
    }

    public function down(): void
    {
        // Listing pages must be removed or reclassified before rolling this
        // migration back; silently truncating their semantic type would make
        // navigation and canonical URLs ambiguous.
        $remaining = $this->db->table('cms_pages')
            ->whereIn('page_type', ['press', 'publications', 'transparency'])
            ->countAllResults();
        if ($remaining > 0) {
            throw new \RuntimeException('Cannot remove publication page types while listing pages still exist.');
        }

        $legacyTypes = "'home','generic','contact','privacy','terms','404','500','maintenance','about','history','events','catalog_listing','collection_index','template_catalog_item','template_event_item'";
        $this->db->query("ALTER TABLE cms_pages MODIFY page_type ENUM(" . $legacyTypes . ") NOT NULL DEFAULT 'generic'");
    }
}
