<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

class SiteIdentitySeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
        if (! isset($langIds['es'], $langIds['en'], $langIds['fr'], $langIds['pt'])) {
            echo "SiteIdentitySeeder: missing languages. Seed CmsLanguageSeeder first.\n";
            return;
        }

        $settings = [
            // `setting_value` stores the canonical base-language value.
            // Localized variants live in `cms_setting_translations`.
            [
                'setting_key'     => 'site_name',
                'setting_value'   => 'TeatroMuseo',
                'setting_type'    => 'string',
                'input_type'      => 'text',
                'setting_group'   => 'identity',
                'is_translatable' => 1,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 10,
                'description'     => 'Nombre del sitio / marca',
                'translations'    => [
                    'en' => 'TeatroMuseo',
                    'fr' => 'TeatroMuseo',
                    'pt' => 'TeatroMuseo',
                ],
            ],
            [
                'setting_key'     => 'site_title',
                'setting_value'   => 'TeatroMuseo',
                'setting_type'    => 'string',
                'input_type'      => 'text',
                'setting_group'   => 'identity',
                'is_translatable' => 1,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 15,
                'description'     => 'Título principal del sitio',
                'translations'    => [
                    'en' => 'TeatroMuseo',
                    'fr' => 'TeatroMuseo',
                    'pt' => 'TeatroMuseo',
                ],
            ],
            [
                'setting_key'     => 'site_tagline',
                'setting_value'   => 'Contenido multilingüe para TeatroMuseo',
                'setting_type'    => 'string',
                'input_type'      => 'textarea',
                'setting_group'   => 'identity',
                'is_translatable' => 1,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 20,
                'description'     => 'Tagline o lema del sitio',
                'translations'    => [
                    'en' => 'Multilingual content for TeatroMuseo',
                    'fr' => 'Contenu multilingue pour TeatroMuseo',
                    'pt' => 'Conteúdo multilíngue para o TeatroMuseo',
                ],
            ],
            [
                'setting_key'     => 'site_description',
                'setting_value'   => 'Sitio base de TeatroMuseo con páginas, noticias y contacto.',
                'setting_type'    => 'string',
                'input_type'      => 'textarea',
                'setting_group'   => 'identity',
                'is_translatable' => 1,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 25,
                'description'     => 'Descripción corta del sitio',
                'translations'    => [
                    'en' => 'TeatroMuseo base site with pages, news, and contact.',
                    'fr' => 'Site de base TeatroMuseo avec des pages, des actualités et un contact.',
                    'pt' => 'Site base do TeatroMuseo com páginas, notícias e contato.',
                ],
            ],
            [
                'setting_key'     => 'site_logo',
                'setting_value'   => '',
                'setting_meta'    => null,
                'setting_type'    => 'file_id',
                'input_type'      => 'image',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 30,
                'description'     => 'Logo principal del sitio',
            ],
            [
                'setting_key'     => 'favicon',
                'setting_value'   => '',
                'setting_meta'    => null,
                'setting_type'    => 'file_id',
                'input_type'      => 'image',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 40,
                'description'     => 'Favicon del sitio',
            ],
            [
                'setting_key'     => 'site_copyright',
                'setting_value'   => '© ' . date('Y') . ' TeatroMuseo. Todos los derechos reservados.',
                'setting_type'    => 'string',
                'input_type'      => 'textarea',
                'setting_group'   => 'identity',
                'is_translatable' => 1,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 50,
                'description'     => 'Texto de copyright del pie de página',
                'translations'    => [
                    'en' => '© ' . date('Y') . ' TeatroMuseo. All rights reserved.',
                    'fr' => '© ' . date('Y') . ' TeatroMuseo. Tous droits réservés.',
                    'pt' => '© ' . date('Y') . ' TeatroMuseo. Todos os direitos reservados.',
                ],
            ],
            [
                'setting_key'     => 'footer_menu_layout',
                'setting_value'   => 'vertical',
                'setting_type'    => 'string',
                'input_type'      => 'select',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 55,
                'description'     => 'Diseño del menú de navegación del pie de página (horizontal o vertical)',
                'setting_meta'    => json_encode(['options' => ['horizontal', 'vertical']], JSON_UNESCAPED_UNICODE),
            ],
            [
                'setting_key'     => 'footer_legal_menu_layout',
                'setting_value'   => 'horizontal',
                'setting_type'    => 'string',
                'input_type'      => 'select',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 60,
                'description'     => 'Diseño del menú legal del pie de página (horizontal o vertical)',
                'setting_meta'    => json_encode(['options' => ['horizontal', 'vertical']], JSON_UNESCAPED_UNICODE),
            ],
            [
                'setting_key'     => 'footer_bg_color',
                'setting_value'   => '#f8fafc',
                'setting_type'    => 'string',
                'input_type'      => 'color',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 70,
                'description'     => 'Color de fondo del pie de página (footer)',
            ],
            [
                'setting_key'     => 'footer_text_color',
                'setting_value'   => '#475569',
                'setting_type'    => 'string',
                'input_type'      => 'color',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 75,
                'description'     => 'Color de texto del pie de página (footer)',
            ],
            [
                'setting_key'     => 'footer_border_color',
                'setting_value'   => '#e2e8f0',
                'setting_type'    => 'string',
                'input_type'      => 'color',
                'setting_group'   => 'identity',
                'is_translatable' => 0,
                'is_public'       => 1,
                'is_active'       => 1,
                'sort_order'      => 80,
                'description'     => 'Color de borde del pie de página (footer)',
            ],
        ];

        foreach ($settings as $setting) {
            $settingId = $this->upsertSetting($setting);

            foreach (($setting['translations'] ?? []) as $langCode => $value) {
                $langId = $langIds[$langCode] ?? null;
                if ($langId === null) {
                    continue;
                }

                $this->upsertSettingTranslation($settingId, $langId, (string) $value);
            }
        }
    }

    /**
     * @param array<int, string> $codes
     * @return array<string, int>
     */
    private function langIds(array $codes): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', $codes)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $setting
     */
    private function upsertSetting(array $setting): int
    {
        $payload = [
            'setting_value'   => $setting['setting_value'] ?? '',
            'setting_type'    => $setting['setting_type'],
            'input_type'      => $setting['input_type'] ?? 'text',
            'setting_group'   => $setting['setting_group'],
            'is_translatable' => $setting['is_translatable'],
            'is_public'       => $setting['is_public'],
            'is_active'       => $setting['is_active'],
            'sort_order'      => $setting['sort_order'],
            'description'     => $setting['description'],
        ];

        if (array_key_exists('setting_meta', $setting)) {
            $payload['setting_meta'] = $setting['setting_meta'];
        }

        $settingId = $this->upsertRecord('cms_settings', [
            'setting_key' => $setting['setting_key'],
        ], $payload);

        if ($settingId === null) {
            throw new \RuntimeException(sprintf('SiteIdentitySeeder: unable to seed setting "%s".', (string) $setting['setting_key']));
        }

        return $settingId;
    }

    private function upsertSettingTranslation(int $settingId, int $languageId, string $value): void
    {
        $this->upsertRecord('cms_setting_translations', [
            'setting_id'  => $settingId,
            'language_id' => $languageId,
        ], [
            'setting_value' => $value,
        ]);
    }
}
