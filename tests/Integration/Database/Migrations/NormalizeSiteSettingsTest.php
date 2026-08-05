<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Migrations;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

require_once APPPATH . 'Database/Migrations/2026-08-05-000001_NormalizeSiteSettings.php';

/**
 * @internal
 */
final class NormalizeSiteSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_setting_connections`');
        $this->db->query('DELETE FROM `cms_setting_translations`');
        $this->db->query('DELETE FROM `cms_settings`');
        $this->db->enableForeignKeyChecks();
    }

    public function testUpRemovesRetiredSettingsAndNormalizesAnalyticsProvider(): void
    {
        foreach ([
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
        ] as $settingKey) {
            $this->insertSetting($settingKey);
        }

        $this->insertSetting('site_name');
        $this->insertSetting('analytics_provider', [
            'input_type'   => 'text',
            'options_json' => null,
            'description'  => 'Legacy analytics provider',
        ]);

        $migrationClass = 'App\\Database\\Migrations\\NormalizeSiteSettings';
        (new $migrationClass())->up();

        $remainingKeys = array_column(
            $this->db->table('cms_settings')
                ->select('setting_key')
                ->orderBy('setting_key', 'ASC')
                ->get()
                ->getResultArray(),
            'setting_key'
        );

        $this->assertSame(['analytics_provider', 'site_name'], $remainingKeys);

        $analyticsProvider = $this->db->table('cms_settings')
            ->where('setting_key', 'analytics_provider')
            ->get()
            ->getRowArray();

        $this->assertIsArray($analyticsProvider);
        $this->assertSame('select', $analyticsProvider['input_type'] ?? null);
        $this->assertSame(
            ['none', 'ga4', 'plausible', 'fathom'],
            array_column(json_decode((string) ($analyticsProvider['options_json'] ?? '[]'), true), 'value')
        );
        $this->assertSame('Proveedor de analytics: none | ga4 | plausible | fathom', $analyticsProvider['description'] ?? null);
    }

    /** @param array<string, mixed> $overrides */
    private function insertSetting(string $settingKey, array $overrides = []): void
    {
        $this->db->table('cms_settings')->insert(array_merge([
            'setting_key'     => $settingKey,
            'setting_value'   => 'legacy-value',
            'setting_type'    => 'string',
            'input_type'      => 'text',
            'setting_group'   => 'general',
            'is_translatable' => 0,
            'is_public'       => 1,
            'is_active'       => 1,
            'sort_order'      => 0,
        ], $overrides));
    }
}
