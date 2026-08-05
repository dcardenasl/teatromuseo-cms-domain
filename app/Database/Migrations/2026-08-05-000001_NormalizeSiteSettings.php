<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes starter-only settings that are not part of the TeatroMuseo runtime contract.
 *
 * @cms-content-data-migration
 */
final class NormalizeSiteSettings extends Migration
{
    /** @var list<string> */
    private array $retiredKeys = [
        'site_title',
        'footer_bg_color',
        'footer_text_color',
        'footer_border_color',
        'contact_admin_email',
        'contact_from_email',
        'contact_site_name',
        'contact_autoreply_message',
        'social_twitter',
        'social_linkedin',
        'social_tiktok',
        'social_pinterest',
        'social_github',
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('cms_settings')) {
            return;
        }

        $this->db->table('cms_settings')
            ->whereIn('setting_key', $this->retiredKeys)
            ->delete();

        $this->db->table('cms_settings')
            ->where('setting_key', 'analytics_provider')
            ->update([
                'input_type' => 'select',
                'options_json' => json_encode([
                    ['value' => 'none', 'label' => 'None'],
                    ['value' => 'ga4', 'label' => 'Google Analytics 4'],
                    ['value' => 'plausible', 'label' => 'Plausible'],
                    ['value' => 'fathom', 'label' => 'Fathom'],
                ], JSON_UNESCAPED_UNICODE),
                'description' => 'Proveedor de analytics: none | ga4 | plausible | fathom',
            ]);
    }

    public function down(): void
    {
        // Retired settings are intentionally not recreated on rollback.
    }
}
