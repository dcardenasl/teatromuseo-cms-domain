<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class SiteMenuSeederTest extends CIUnitTestCase
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

        $expectedMainLabels = [
            'es' => ['Inicio', 'Nosotros', 'Cartelera', 'Festivales', 'Museo', 'Educación', 'Multimedia', 'Prensa', 'Noticias', 'Contacto'],
            'en' => ['Home', 'About', 'Programming', 'Festivals', 'Museum', 'Education', 'Media', 'Press', 'News', 'Contact'],
            'fr' => ['Accueil', 'À propos', 'Programmation', 'Festivals', 'Musée', 'Éducation', 'Médias', 'Presse', 'Actualités', 'Contact'],
            'pt' => ['Início', 'Sobre', 'Programação', 'Festivais', 'Museu', 'Educação', 'Mídia', 'Imprensa', 'Notícias', 'Contato'],
        ];

        foreach ($expectedMainLabels as $langCode => $expectedLabels) {
            $this->assertSame($expectedLabels, $this->menuLabels((int) $mainMenu['id'], $langCode, null));
        }

        $nosotrosId = $this->menuItemId((int) $mainMenu['id'], 'Nosotros');
        $this->assertNotNull($nosotrosId);
        $this->assertSame(['Quiénes Somos', 'Historia'], $this->menuLabels((int) $mainMenu['id'], 'es', $nosotrosId));

        $carteleraId = $this->menuItemId((int) $mainMenu['id'], 'Cartelera');
        $this->assertNotNull($carteleraId);
        $this->assertSame(['Compañías'], $this->menuLabels((int) $mainMenu['id'], 'es', $carteleraId));

        $museoId = $this->menuItemId((int) $mainMenu['id'], 'Museo');
        $this->assertNotNull($museoId);
        $this->assertSame(['Colección', 'Exposiciones', 'Personas'], $this->menuLabels((int) $mainMenu['id'], 'es', $museoId));

        $footerMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'footer')
            ->get()
            ->getRowArray();

        $this->assertNotNull($footerMenu);

        $expectedFooterLabels = [
            'es' => ['Inicio', 'Quiénes Somos', 'Historia', 'Cartelera', 'Festivales', 'Exposiciones', 'Educación', 'Multimedia', 'Prensa', 'Noticias', 'Contacto'],
            'en' => ['Home', 'About Us', 'History', 'Programming', 'Festivals', 'Exhibitions', 'Education', 'Media', 'Press', 'News', 'Contact'],
            'fr' => ['Accueil', 'À propos', 'Histoire', 'Programmation', 'Festivals', 'Expositions', 'Éducation', 'Médias', 'Presse', 'Actualités', 'Contact'],
            'pt' => ['Início', 'Sobre Nós', 'História', 'Programação', 'Festivais', 'Exposições', 'Educação', 'Mídia', 'Imprensa', 'Notícias', 'Contato'],
        ];

        foreach ($expectedFooterLabels as $langCode => $expectedLabels) {
            $this->assertSame($expectedLabels, $this->menuLabels((int) $footerMenu['id'], $langCode, null));
        }

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
