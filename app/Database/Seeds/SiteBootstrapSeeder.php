<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Services;

/**
 * Seeds the TeatroMuseo bootstrap content: languages, settings, structural
 * pages, institutional/legal pages, collections, menus. Migrations define
 * the CMS structure; this seeder is not required for the wizard to run.
 *
 * The starter kit's block-coverage demo pages (portfolio, components, media,
 * landing) and the synthetic pilot entries used to validate the public IA
 * were removed 2026-08-02 — this project's public site must only ever show
 * content that actually exists on the legacy teatromuseo.cl site or was
 * migrated from it. Do not reintroduce a seeder that injects placeholder
 * content into a real collection.
 */
class SiteBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CmsLanguageSeeder::class);
        $this->call(CmsFormSeeder::class);
        $this->call(SiteIdentitySeeder::class);
        $this->call(SiteIntegrationSettingsSeeder::class);
        $this->call(AnalyticsSeeder::class);
        $this->call(SiteSocialLinksSeeder::class);
        $this->call(CmsBlockTypeSeeder::class);
        $this->call(CmsCollectionGridSourceSeeder::class);
        $this->call(TeatroMuseoBlockTypeSeeder::class);
        $this->call(CmsTeatroMuseoCollectionSeeder::class);
        $this->call(CmsTeatroMuseoPageStructureSeeder::class);
        $this->call(SitePagesSeeder::class);
        $this->call(CmsTeatroMuseoInstitutionalPagesSeeder::class);
        $this->call(CmsTeatroMuseoLegalPagesSeeder::class);
        $this->call(CmsTeatroMuseoPublicListingPagesSeeder::class);
        $this->call(CmsPageBlockSeeder::class);
        $this->call(CmsTeatroMuseoNavigationSeeder::class);
        $this->call(CmsTeatroMuseoRedirectSeeder::class);
        $this->call(CmsContentSanitizationSeeder::class);

        // Seeders write directly for deterministic bootstrap performance, so
        // finish through the same canonical reference synchronizer used by the
        // application services. A fresh install is immediately consistent and
        // never depends on a repair command.
        Services::fileReferenceSynchronizer(false)->rebuildAll();
    }
}
