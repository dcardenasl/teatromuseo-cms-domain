<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterCmsPagesForPublicListingPageTypes extends Migration
{
    public function up(): void
    {
        // Drop the generated column
        $this->db->query('ALTER TABLE `cms_pages` DROP COLUMN `type_singleton`');

        // Modify page_type ENUM
        $this->db->query("ALTER TABLE `cms_pages` MODIFY COLUMN `page_type` ENUM('home', 'generic', 'contact', 'privacy', 'terms', '404', '500', 'maintenance', 'about', 'history', 'events', 'catalog_listing', 'collection_index', 'template_catalog_item', 'template_event_item') NOT NULL DEFAULT 'generic'");

        // Re-add generated column with VARCHAR(30)
        $this->db->query("ALTER TABLE `cms_pages` ADD COLUMN `type_singleton` VARCHAR(30) GENERATED ALWAYS AS (
            CASE WHEN `page_type` IN ('home','404','500','maintenance','contact','privacy','terms','about','history','events','catalog_listing','template_catalog_item','template_event_item') 
            AND `deleted_at` IS NULL THEN `page_type` ELSE NULL END
        ) STORED");
    }

    public function down(): void
    {
        // normalización de filas sobre los valores que desaparecen
        $this->db->query("UPDATE `cms_pages` SET `page_type` = 'generic' WHERE `page_type` IN ('about', 'history', 'events', 'catalog_listing', 'template_catalog_item', 'template_event_item')");

        $this->db->query('ALTER TABLE `cms_pages` DROP COLUMN `type_singleton`');

        $this->db->query("ALTER TABLE `cms_pages` MODIFY COLUMN `page_type` ENUM('home', 'generic', 'contact', 'privacy', 'terms', '404', '500', 'maintenance', 'collection_index') NOT NULL DEFAULT 'generic'");

        $this->db->query("ALTER TABLE `cms_pages` ADD COLUMN `type_singleton` VARCHAR(20) GENERATED ALWAYS AS (
            CASE WHEN `page_type` IN ('home','404','500','maintenance','contact','privacy','terms') 
            AND `deleted_at` IS NULL THEN `page_type` ELSE NULL END
        ) STORED");
    }
}
