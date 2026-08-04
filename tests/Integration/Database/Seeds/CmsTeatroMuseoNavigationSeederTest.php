<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class CmsTeatroMuseoNavigationSeederTest extends CIUnitTestCase
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
        $tables = [
            'cms_file_references',
            'cms_block_instance_translations',
            'cms_block_instances',
            'cms_content_blocks',
            'cms_form_field_translations',
            'cms_form_fields',
            'cms_form_translations',
            'cms_forms',
            'cms_menu_item_translations',
            'cms_menu_items',
            'cms_menu_translations',
            'cms_menus',
            'cms_entry_categories',
            'cms_entry_tags',
            'cms_entry_translations',
            'cms_entries',
            'cms_category_translations',
            'cms_categories',
            'cms_tag_translations',
            'cms_tags',
            'cms_page_translations',
            'cms_pages',
            'cms_collection_translations',
            'cms_collections',
            'cms_setting_translations',
            'cms_settings',
            'cms_languages',
        ];

        foreach ($tables as $table) {
            $this->db->query("DELETE FROM `{$table}`");
        }
        $this->db->enableForeignKeyChecks();
    }

    public function testMenuSeederSyncsMainAndFooterWithoutDuplicateTopLevelItems(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $mainMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'main')
            ->get()
            ->getRowArray();

        $this->assertNotNull($mainMenu);

        // Grouped 2026-07-31: 10 flat top-level entries -> 7 entries behind
        // 4 dropdowns (Nosotros, Programación, Museo, Prensa y Medios).
        $expectedMainLabels = [
            'es' => ['Inicio', 'Nosotros', 'Programación', 'Museo', 'TeatroEscuela', 'Prensa y Medios', 'Contacto'],
            'en' => ['Home', 'About', 'Programming', 'Museum', 'TeatroEscuela', 'Press & Media', 'Contact'],
            'fr' => ['Accueil', 'À propos', 'Programmation', 'Musée', 'TeatroEscuela', 'Presse et Médias', 'Contact'],
            'pt' => ['Início', 'Sobre', 'Programação', 'Museu', 'TeatroEscuela', 'Imprensa e Mídia', 'Contato'],
        ];

        foreach ($expectedMainLabels as $langCode => $expectedLabels) {
            $this->assertSame($expectedLabels, $this->menuLabels((int) $mainMenu['id'], $langCode, null));
        }

        $nosotrosId = $this->menuItemId((int) $mainMenu['id'], 'Nosotros');
        $this->assertNotNull($nosotrosId);
        $this->assertSame(['Quiénes Somos', 'Historia'], $this->menuLabels((int) $mainMenu['id'], 'es', $nosotrosId));

        $programacionId = $this->menuItemId((int) $mainMenu['id'], 'Programación');
        $this->assertNotNull($programacionId);
        $this->assertSame(['Cartelera', 'Festivales', 'Compañías'], $this->menuLabels((int) $mainMenu['id'], 'es', $programacionId));

        $museoId = $this->menuItemId((int) $mainMenu['id'], 'Museo');
        $this->assertNotNull($museoId);
        $this->assertSame(['Colección', 'Exposiciones', 'Personas'], $this->menuLabels((int) $mainMenu['id'], 'es', $museoId));

        $prensaId = $this->menuItemId((int) $mainMenu['id'], 'Prensa y Medios');
        $this->assertNotNull($prensaId);
        $this->assertSame(['Noticias', 'Multimedia', 'Prensa'], $this->menuLabels((int) $mainMenu['id'], 'es', $prensaId));

        $footerMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'footer')
            ->get()
            ->getRowArray();

        $this->assertNotNull($footerMenu);

        // Grouped 2026-07-31: flat 11-item list -> 3 labeled columns
        // (Explora, Institución, Prensa y Medios).
        $expectedFooterLabels = [
            'es' => ['Explora', 'Institución', 'Prensa y Medios'],
            'en' => ['Explore', 'Institution', 'Press & Media'],
            'fr' => ['Explorer', 'Institution', 'Presse et Médias'],
            'pt' => ['Explorar', 'Instituição', 'Imprensa e Mídia'],
        ];

        foreach ($expectedFooterLabels as $langCode => $expectedLabels) {
            $this->assertSame($expectedLabels, $this->menuLabels((int) $footerMenu['id'], $langCode, null));
        }

        $exploreId = $this->menuItemId((int) $footerMenu['id'], 'Explora');
        $this->assertNotNull($exploreId);
        $this->assertSame(['Inicio', 'Cartelera', 'Festivales', 'Colección del Museo'], $this->menuLabels((int) $footerMenu['id'], 'es', $exploreId));

        $institucionId = $this->menuItemId((int) $footerMenu['id'], 'Institución');
        $this->assertNotNull($institucionId);
        $this->assertSame(['Quiénes Somos', 'Historia', 'TeatroEscuela', 'Contacto'], $this->menuLabels((int) $footerMenu['id'], 'es', $institucionId));

        $footerPrensaId = $this->menuItemId((int) $footerMenu['id'], 'Prensa y Medios');
        $this->assertNotNull($footerPrensaId);
        $this->assertSame(['Noticias', 'Multimedia', 'Prensa'], $this->menuLabels((int) $footerMenu['id'], 'es', $footerPrensaId));

        $legalMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'legal')
            ->get()
            ->getRowArray();

        $this->assertNotNull($legalMenu);

        $expectedLegalLabels = [
            'es' => ['Aviso Legal', 'Política de Privacidad', 'Política de Cookies', 'Derechos de Datos', 'Términos de Servicio', 'Transparencia', 'Accesibilidad'],
            'en' => ['Legal Notice', 'Privacy Policy', 'Cookie Policy', 'Data Rights', 'Terms of Service', 'Transparency', 'Accessibility'],
            'fr' => ['Mentions légales', 'Politique de confidentialité', 'Politique de cookies', 'Droits sur les données', 'Conditions d’utilisation', 'Transparence', 'Accessibilité'],
            'pt' => ['Aviso Jurídico', 'Política de Privacidade', 'Política de Cookies', 'Direitos sobre os Dados', 'Termos de Uso', 'Transparência', 'Acessibilidade'],
        ];

        foreach ($expectedLegalLabels as $langCode => $expectedLabels) {
            $this->assertSame($expectedLabels, $this->menuLabels((int) $legalMenu['id'], $langCode, null));
        }
    }

    private function menuItemId(int $menuId, string $label): ?int
    {
        $row = $this->db->table('cms_menu_items mi')
            ->select('mi.id')
            ->join('cms_menu_item_translations mt', 'mt.menu_item_id = mi.id')
            ->join('cms_languages l', 'l.id = mt.language_id')
            ->where('mi.menu_id', $menuId)
            ->where('mi.parent_id IS NULL', null, false)
            ->where('l.code', 'es')
            ->where('mt.label', $label)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @return list<string>
     */
    private function menuLabels(int $menuId, string $langCode, ?int $parentId): array
    {
        $builder = $this->db->table('cms_menu_items mi')
            ->select('mt.label')
            ->join('cms_menu_item_translations mt', 'mt.menu_item_id = mi.id')
            ->join('cms_languages l', 'l.id = mt.language_id')
            ->where('mi.menu_id', $menuId)
            ->where('mi.is_active', 1)
            ->where('l.code', $langCode)
            ->orderBy('mi.sort_order', 'ASC')
            ->orderBy('mi.id', 'ASC');

        if ($parentId === null) {
            $builder->where('mi.parent_id IS NULL', null, false);
        } else {
            $builder->where('mi.parent_id', $parentId);
        }

        $rows = $builder->get()->getResultArray();

        return array_map(
            static fn (array $row): string => (string) ($row['label'] ?? ''),
            $rows
        );
    }
}
