<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Seeds the public TeatroMuseo information architecture into the standard
 * `main` and `footer` menus consumed by the public website.
 *
 * This seeder is intentionally structural: links point to collection index
 * pages and base pages, never to content entries. It can therefore run before
 * the legacy content migration and gives migrated entries a stable IA from
 * day one.
 *
 * The two menu trees are canonical bootstrap structures. Re-running this
 * seeder removes stale items from these two menus, while leaving the legal
 * menu and unrelated menus untouched. This is suitable for the development
 * bootstrap and the controlled migration cutover; curated production menus
 * should be changed through the CMS instead.
 *
 * Grouped 2026-07-31: main menu was 10 flat top-level entries (too many for
 * a header row); regrouped into 7 entries behind 4 dropdowns (Nosotros,
 * Programación, Museo, Prensa y Medios). Footer was a single flat 11-item
 * list; regrouped into 3 labeled columns (Explora, Institución, Prensa y
 * Medios) — teatromuseo-web's footer.php was updated in the same change to
 * render `no_link` items with children as column headers instead of
 * ignoring them.
 */
final class CmsTeatroMuseoNavigationSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $languages = $this->languageIds();
        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoNavigationSeeder: missing languages; skipping.\n";

            return;
        }

        $homePageId = $this->pageIdByType('home');
        $contactPageId = $this->pageIdByType('contact');
        $aboutPageId = $this->pageIdBySlug(['nosotros', 'about', 'a-propos', 'sobre-nos']);
        $historyPageId = $this->pageIdBySlug(['historia', 'history', 'histoire', 'nossa-historia']);
        // Domain pages are resolved by their stable semantic type. A slug
        // fallback would reintroduce locale-specific historical URLs.
        $eventsPageId = $this->pageIdByType('events');
        $catalogListingPageId = $this->pageIdByType('catalog_listing');
        $pressPageId = $this->pageIdByType('press');
        $publicationsPageId = $this->pageIdByType('publications');
        $transparencyPageId = $this->pageIdByType('transparency');

        if ($homePageId === null || $contactPageId === null) {
            echo "CmsTeatroMuseoNavigationSeeder: missing home or contact page; skipping.\n";

            return;
        }

        $this->seedMainMenu($languages, $homePageId, $contactPageId, $aboutPageId, $historyPageId, $eventsPageId, $catalogListingPageId, $pressPageId, $publicationsPageId);
        $this->seedFooterMenu($languages, $homePageId, $contactPageId, $aboutPageId, $historyPageId, $eventsPageId, $catalogListingPageId, $publicationsPageId);
        $this->seedTransparencyInLegalMenu($languages, $transparencyPageId);
    }

    /** @param array<string, int> $languages */
    private function seedTransparencyInLegalMenu(array $languages, ?int $pageId): void
    {
        if ($pageId === null) {
            return;
        }

        $menuId = $this->upsertMenu('legal', 'footer_secondary', [
            'es' => 'Legal', 'en' => 'Legal', 'fr' => 'Légal', 'pt' => 'Legal',
        ], $languages);
        $this->addPageItem($menuId, $pageId, null, 6, [
            'es' => 'Transparencia', 'en' => 'Transparency', 'fr' => 'Transparence', 'pt' => 'Transparência',
        ], $languages);
    }

    /** @param array<string, int> $languages */
    private function seedMainMenu(
        array $languages,
        int $homePageId,
        int $contactPageId,
        ?int $aboutPageId,
        ?int $historyPageId,
        ?int $eventsPageId,
        ?int $catalogListingPageId,
        ?int $pressPageId,
        ?int $publicationsPageId
    ): void {
        $menuId = $this->upsertMenu('main', 'header', [
            'es' => 'Navegación principal',
            'en' => 'Main navigation',
            'fr' => 'Navigation principale',
            'pt' => 'Navegação principal',
        ], $languages);
        $this->removeLegacyCollectionMenuItems($menuId, 'publicaciones');
        $keepIds = [];
        $sortOrder = 1;

        $homeId = $this->addPageItem($menuId, $homePageId, null, $sortOrder++, [
            'es' => 'Inicio',
            'en' => 'Home',
            'fr' => 'Accueil',
            'pt' => 'Início',
        ], $languages);
        $this->appendId($keepIds, $homeId);

        $keepIds = array_merge($keepIds, $this->addPageGroup($menuId, $sortOrder++, [
            'es' => 'Nosotros',
            'en' => 'About',
            'fr' => 'À propos',
            'pt' => 'Sobre',
        ], [
            [
                'page_id' => $aboutPageId,
                'sort_order' => 1,
                'es' => 'Quiénes Somos',
                'en' => 'About Us',
                'fr' => 'À propos',
                'pt' => 'Sobre Nós',
            ],
            [
                'page_id' => $historyPageId,
                'sort_order' => 2,
                'es' => 'Historia',
                'en' => 'History',
                'fr' => 'Histoire',
                'pt' => 'História',
            ],
            [
                'page_id' => $pressPageId,
                'sort_order' => 3,
                'es' => 'Prensa',
                'en' => 'Press',
                'fr' => 'Presse',
                'pt' => 'Imprensa',
            ],
        ], $languages));

        // "Programación" dropdown — Cartelera, Festivales, Compañías
        $programmingGroupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder++, [
            'es' => 'Programación',
            'en' => 'Programming',
            'fr' => 'Programmation',
            'pt' => 'Programação',
        ], $languages);
        $this->appendId($keepIds, $programmingGroupId);

        $programmingChildSortOrder = 1;
        $carteleraId = $eventsPageId !== null
            ? $this->addPageItem($menuId, $eventsPageId, $programmingGroupId, $programmingChildSortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Listings',
                'fr' => 'Programme',
                'pt' => 'Cartaz',
            ], $languages)
            : $this->addEventListingItem($menuId, $programmingGroupId, $programmingChildSortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Listings',
                'fr' => 'Programme',
                'pt' => 'Cartaz',
            ], $languages);
        $this->appendId($keepIds, $carteleraId);

        $festivalsId = $this->addCollectionItem($menuId, 'festivales', $programmingGroupId, $programmingChildSortOrder++, [
            'es' => 'Festivales',
            'en' => 'Festivals',
            'fr' => 'Festivals',
            'pt' => 'Festivais',
        ], $languages);
        $this->appendId($keepIds, $festivalsId);

        $companiasId = $this->addCollectionItem($menuId, 'companias', $programmingGroupId, $programmingChildSortOrder, [
            'es' => 'Compañías',
            'en' => 'Companies',
            'fr' => 'Compagnies',
            'pt' => 'Companhias',
        ], $languages);
        $this->appendId($keepIds, $companiasId);

        $museoGroupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder++, [
            'es' => 'Museo',
            'en' => 'Museum',
            'fr' => 'Musée',
            'pt' => 'Museu',
        ], $languages);
        $this->appendId($keepIds, $museoGroupId);

        $museoChildSortOrder = 1;
        if ($catalogListingPageId !== null) {
            $coleccionId = $this->addPageItem($menuId, $catalogListingPageId, $museoGroupId, $museoChildSortOrder++, [
                'es' => 'Colección',
                'en' => 'Collection',
                'fr' => 'Collection',
                'pt' => 'Coleção',
            ], $languages);
            $this->appendId($keepIds, $coleccionId);
        }

        $exposicionesId = $this->addCollectionItem($menuId, 'exposiciones', $museoGroupId, $museoChildSortOrder++, [
            'es' => 'Exposiciones',
            'en' => 'Exhibitions',
            'fr' => 'Expositions',
            'pt' => 'Exposições',
        ], $languages);
        $this->appendId($keepIds, $exposicionesId);

        $personasId = $this->addCollectionItem($menuId, 'personas', $museoGroupId, $museoChildSortOrder, [
            'es' => 'Personas',
            'en' => 'People',
            'fr' => 'Personnes',
            'pt' => 'Pessoas',
        ], $languages);
        $this->appendId($keepIds, $personasId);

        $coursesId = $this->addCollectionItem($menuId, 'teatroescuela', null, $sortOrder++, [
            'es' => 'TeatroEscuela',
            'en' => 'TeatroEscuela',
            'fr' => 'TeatroEscuela',
            'pt' => 'TeatroEscuela',
        ], $languages);
        $this->appendId($keepIds, $coursesId);

        // "Prensa y Medios" dropdown — Noticias, Multimedia, Prensa
        $pressGroupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder++, [
            'es' => 'Prensa y Medios',
            'en' => 'Press & Media',
            'fr' => 'Presse et Médias',
            'pt' => 'Imprensa e Mídia',
        ], $languages);
        $this->appendId($keepIds, $pressGroupId);

        $pressChildSortOrder = 1;
        $newsId = $this->addCollectionItem($menuId, 'noticias', $pressGroupId, $pressChildSortOrder++, [
            'es' => 'Noticias',
            'en' => 'News',
            'fr' => 'Actualités',
            'pt' => 'Notícias',
        ], $languages);
        $this->appendId($keepIds, $newsId);

        $videosId = $this->addCollectionItem($menuId, 'videos', $pressGroupId, $pressChildSortOrder++, [
            'es' => 'Multimedia',
            'en' => 'Media',
            'fr' => 'Médias',
            'pt' => 'Mídia',
        ], $languages);
        $this->appendId($keepIds, $videosId);

        if ($publicationsPageId !== null) {
            $publicationsId = $this->addPageItem($menuId, $publicationsPageId, $pressGroupId, $pressChildSortOrder, [
                'es' => 'Publicaciones',
                'en' => 'Publications',
                'fr' => 'Publications',
                'pt' => 'Publicações',
            ], $languages);
            $this->appendId($keepIds, $publicationsId);
        }

        $contactId = $this->addPageItem($menuId, $contactPageId, null, $sortOrder, [
            'es' => 'Contacto',
            'en' => 'Contact',
            'fr' => 'Contact',
            'pt' => 'Contato',
        ], $languages);
        $this->appendId($keepIds, $contactId);

        $this->pruneMenuItems($menuId, $keepIds);
    }

    /** @param array<string, int> $languages */
    private function seedFooterMenu(
        array $languages,
        int $homePageId,
        int $contactPageId,
        ?int $aboutPageId,
        ?int $historyPageId,
        ?int $eventsPageId,
        ?int $catalogListingPageId,
        ?int $publicationsPageId
    ): void {
        $menuId = $this->upsertMenu('footer', 'footer', [
            'es' => 'Pie de página',
            'en' => 'Footer navigation',
            'fr' => 'Navigation de pied de page',
            'pt' => 'Navegação de rodapé',
        ], $languages);
        $this->removeLegacyCollectionMenuItems($menuId, 'publicaciones');
        $keepIds = [];
        $sortOrder = 1;

        // "Explora" column — Inicio, Cartelera, Festivales, Colección del Museo
        $exploreGroupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder++, [
            'es' => 'Explora',
            'en' => 'Explore',
            'fr' => 'Explorer',
            'pt' => 'Explorar',
        ], $languages);
        $this->appendId($keepIds, $exploreGroupId);

        $exploreChildSortOrder = 1;
        $homeId = $this->addPageItem($menuId, $homePageId, $exploreGroupId, $exploreChildSortOrder++, [
            'es' => 'Inicio',
            'en' => 'Home',
            'fr' => 'Accueil',
            'pt' => 'Início',
        ], $languages);
        $this->appendId($keepIds, $homeId);

        $carteleraId = $eventsPageId !== null
            ? $this->addPageItem($menuId, $eventsPageId, $exploreGroupId, $exploreChildSortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Listings',
                'fr' => 'Programme',
                'pt' => 'Cartaz',
            ], $languages)
            : $this->addEventListingItem($menuId, $exploreGroupId, $exploreChildSortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Listings',
                'fr' => 'Programme',
                'pt' => 'Cartaz',
            ], $languages);
        $this->appendId($keepIds, $carteleraId);

        $festivalsId = $this->addCollectionItem($menuId, 'festivales', $exploreGroupId, $exploreChildSortOrder++, [
            'es' => 'Festivales',
            'en' => 'Festivals',
            'fr' => 'Festivals',
            'pt' => 'Festivais',
        ], $languages);
        $this->appendId($keepIds, $festivalsId);

        if ($catalogListingPageId !== null) {
            $coleccionId = $this->addPageItem($menuId, $catalogListingPageId, $exploreGroupId, $exploreChildSortOrder, [
                'es' => 'Colección del Museo',
                'en' => 'Museum Collection',
                'fr' => 'Collection du musée',
                'pt' => 'Coleção do museu',
            ], $languages);
            $this->appendId($keepIds, $coleccionId);
        }

        // "Institución" column — Quiénes Somos, Historia, TeatroEscuela, Contacto
        $institutionGroupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder++, [
            'es' => 'Institución',
            'en' => 'Institution',
            'fr' => 'Institution',
            'pt' => 'Instituição',
        ], $languages);
        $this->appendId($keepIds, $institutionGroupId);

        $institutionChildSortOrder = 1;
        if ($aboutPageId !== null) {
            $aboutId = $this->addPageItem($menuId, $aboutPageId, $institutionGroupId, $institutionChildSortOrder++, [
                'es' => 'Quiénes Somos',
                'en' => 'About Us',
                'fr' => 'À propos',
                'pt' => 'Sobre Nós',
            ], $languages);
            $this->appendId($keepIds, $aboutId);
        }

        if ($historyPageId !== null) {
            $historyId = $this->addPageItem($menuId, $historyPageId, $institutionGroupId, $institutionChildSortOrder++, [
                'es' => 'Historia',
                'en' => 'History',
                'fr' => 'Histoire',
                'pt' => 'História',
            ], $languages);
            $this->appendId($keepIds, $historyId);
        }

        $coursesId = $this->addCollectionItem($menuId, 'teatroescuela', $institutionGroupId, $institutionChildSortOrder++, [
            'es' => 'TeatroEscuela',
            'en' => 'TeatroEscuela',
            'fr' => 'TeatroEscuela',
            'pt' => 'TeatroEscuela',
        ], $languages);
        $this->appendId($keepIds, $coursesId);

        $contactId = $this->addPageItem($menuId, $contactPageId, $institutionGroupId, $institutionChildSortOrder, [
            'es' => 'Contacto',
            'en' => 'Contact',
            'fr' => 'Contact',
            'pt' => 'Contato',
        ], $languages);
        $this->appendId($keepIds, $contactId);

        // "Prensa y Medios" column — Noticias, Multimedia, Publicaciones
        $pressGroupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder, [
            'es' => 'Prensa y Medios',
            'en' => 'Press & Media',
            'fr' => 'Presse et Médias',
            'pt' => 'Imprensa e Mídia',
        ], $languages);
        $this->appendId($keepIds, $pressGroupId);

        $pressChildSortOrder = 1;
        foreach ([
            ['collection_key' => 'noticias', 'es' => 'Noticias', 'en' => 'News', 'fr' => 'Actualités', 'pt' => 'Notícias'],
            ['collection_key' => 'videos', 'es' => 'Multimedia', 'en' => 'Media', 'fr' => 'Médias', 'pt' => 'Mídia'],
        ] as $definition) {
            $itemId = $this->addCollectionItem(
                $menuId,
                $definition['collection_key'],
                $pressGroupId,
                $pressChildSortOrder,
                [
                    'es' => $definition['es'],
                    'en' => $definition['en'],
                    'fr' => $definition['fr'],
                    'pt' => $definition['pt'],
                ],
                $languages
            );
            $this->appendId($keepIds, $itemId);
            $pressChildSortOrder++;
        }
        if ($publicationsPageId !== null) {
            $publicationsId = $this->addPageItem($menuId, $publicationsPageId, $pressGroupId, $pressChildSortOrder, [
                'es' => 'Publicaciones', 'en' => 'Publications', 'fr' => 'Publications', 'pt' => 'Publicações',
            ], $languages);
            $this->appendId($keepIds, $publicationsId);
            $pressChildSortOrder++;
        }

        $this->pruneMenuItems($menuId, $keepIds);
    }

    /**
     * @param array{es: string, en: string, fr: string, pt: string} $translations
     * @param list<array{page_id: int|null, sort_order: int, es: string, en: string, fr: string, pt: string}> $children
     * @param array<string, int> $languages
     * @return list<int>
     */
    private function addPageGroup(
        int $menuId,
        int $sortOrder,
        array $translations,
        array $children,
        array $languages
    ): array {
        $availableChildren = [];
        foreach ($children as $child) {
            if (($child['page_id'] ?? null) !== null) {
                $availableChildren[] = $child;
            }
        }

        if ($availableChildren === []) {
            return [];
        }

        $groupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder, $translations, $languages);
        $keepIds = [$groupId];

        foreach ($availableChildren as $child) {
            $itemId = $this->addPageItem(
                $menuId,
                (int) $child['page_id'],
                $groupId,
                (int) $child['sort_order'],
                [
                    'es' => $child['es'],
                    'en' => $child['en'],
                    'fr' => $child['fr'],
                    'pt' => $child['pt'],
                ],
                $languages
            );
            $this->appendId($keepIds, $itemId);
        }

        return $keepIds;
    }

    /**
     * @param array<string, int> $languages
     * @param list<array{collection_key: string, sort_order: int, es: string, en: string}> $children
     * @param array{es: string, en: string} $translations
     * @return list<int>
     */
    private function addGroup(
        int $menuId,
        int $sortOrder,
        array $children,
        array $translations,
        array $languages
    ): array {
        $availableChildren = [];
        foreach ($children as $child) {
            if ($this->collectionIdByKey($child['collection_key']) !== null) {
                $availableChildren[] = $child;
            }
        }

        if ($availableChildren === []) {
            return [];
        }

        $groupId = $this->upsertMenuItemNoLink($menuId, null, $sortOrder, $translations, $languages);
        $keepIds = [$groupId];

        foreach ($availableChildren as $child) {
            $itemId = $this->addCollectionItem(
                $menuId,
                $child['collection_key'],
                $groupId,
                $child['sort_order'],
                ['es' => $child['es'], 'en' => $child['en']],
                $languages
            );
            $this->appendId($keepIds, $itemId);
        }

        return $keepIds;
    }

    /**
     * @param array{es: string, en: string} $translations
     * @param array<string, int> $languages
     */
    private function addPageItem(
        int $menuId,
        int $pageId,
        ?int $parentId,
        int $sortOrder,
        array $translations,
        array $languages
    ): ?int {
        return $this->upsertMenuItem($menuId, 'page', [
            'parent_id' => $parentId,
            'page_id' => $pageId,
            'entry_id' => null,
            'collection_id' => null,
            'sort_order' => $sortOrder,
        ], $translations, $languages);
    }

    /**
     * @param array{es: string, en: string} $translations
     * @param array<string, int> $languages
     */
    private function addCollectionItem(
        int $menuId,
        string $collectionKey,
        ?int $parentId,
        int $sortOrder,
        array $translations,
        array $languages
    ): ?int {
        $collectionId = $this->collectionIdByKey($collectionKey);
        if ($collectionId === null) {
            return null;
        }

        return $this->upsertMenuItem($menuId, 'collection_listing', [
            'parent_id' => $parentId,
            'page_id' => null,
            'entry_id' => null,
            'collection_id' => $collectionId,
            'sort_order' => $sortOrder,
        ], $translations, $languages);
    }

    /**
     * @param array{es: string, en: string, fr: string, pt: string} $translations
     * @param array<string, int> $languages
     */
    private function addEventListingItem(
        int $menuId,
        ?int $parentId,
        int $sortOrder,
        array $translations,
        array $languages
    ): ?int {
        return $this->upsertMenuItem($menuId, 'event_listing', [
            'parent_id' => $parentId,
            'page_id' => null,
            'entry_id' => null,
            'collection_id' => null,
            'sort_order' => $sortOrder,
        ], $translations, $languages);
    }

    /** @param array{es: string, en: string, fr: string, pt: string} $translations @param array<string, int> $languages */
    private function upsertMenu(string $menuKey, string $location, array $translations, array $languages): int
    {
        $menuId = $this->upsertRecord('cms_menus', ['menu_key' => $menuKey], [
            'location' => $location,
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        if ($menuId === null) {
            throw new \RuntimeException(sprintf('Unable to seed menu "%s".', $menuKey));
        }

        foreach ($translations as $language => $name) {
            $languageId = $languages[$language] ?? null;
            if ($languageId === null) {
                continue;
            }

            $this->upsertRecord('cms_menu_translations', [
                'menu_id' => $menuId,
                'language_id' => $languageId,
            ], ['name' => $name]);
        }

        return $menuId;
    }

    /**
     * @param array{parent_id: int|null, page_id: int|null, entry_id: int|null, collection_id: int|null, sort_order: int} $references
     * @param array{es: string, en: string, fr: string, pt: string} $translations
     * @param array<string, int> $languages
     */
    private function upsertMenuItem(
        int $menuId,
        string $linkType,
        array $references,
        array $translations,
        array $languages
    ): ?int {
        $itemId = $this->upsertRecord('cms_menu_items', [
            'menu_id' => $menuId,
            'parent_id' => $references['parent_id'],
            'link_type' => $linkType,
            'page_id' => $references['page_id'],
            'entry_id' => $references['entry_id'],
            'collection_id' => $references['collection_id'],
            'sort_order' => $references['sort_order'],
        ], [
            'link_target' => '_self',
            'icon' => null,
            'css_class' => null,
            'is_active' => 1,
        ]);

        if ($itemId === null) {
            throw new \RuntimeException(sprintf('Unable to seed menu item "%s".', $linkType));
        }

        foreach ($translations as $language => $label) {
            $languageId = $languages[$language] ?? null;
            if ($languageId === null) {
                continue;
            }

            $this->upsertRecord('cms_menu_item_translations', [
                'menu_item_id' => $itemId,
                'language_id' => $languageId,
            ], [
                'label' => $label,
                'custom_url' => null,
            ]);
        }

        return $itemId;
    }

    /** @param array{es: string, en: string, fr: string, pt: string} $translations @param array<string, int> $languages */
    private function upsertMenuItemNoLink(
        int $menuId,
        ?int $parentId,
        int $sortOrder,
        array $translations,
        array $languages
    ): int {
        $itemId = $this->upsertMenuItem($menuId, 'no_link', [
            'parent_id' => $parentId,
            'page_id' => null,
            'entry_id' => null,
            'collection_id' => null,
            'sort_order' => $sortOrder,
        ], $translations, $languages);

        if ($itemId === null) {
            throw new \RuntimeException('Unable to seed menu group.');
        }

        return $itemId;
    }

    /**
     * @param list<string> $slugs
     */
    private function pageIdBySlug(array $slugs): ?int
    {
        $row = $this->db->table('cms_pages')
            ->select('cms_pages.id')
            ->join('cms_page_translations', 'cms_page_translations.page_id = cms_pages.id')
            ->where('cms_pages.deleted_at IS NULL', null, false)
            ->whereIn('cms_page_translations.slug', $slugs)
            ->orderBy('cms_pages.id', 'ASC')
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', ['es', 'en', 'fr', 'pt'])
            ->get()
            ->getResultArray();
        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }

    private function pageIdByType(string $pageType): ?int
    {
        $row = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', $pageType)
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function collectionIdByKey(string $collectionKey): ?int
    {
        $row = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', $collectionKey)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function removeLegacyCollectionMenuItems(int $menuId, string $collectionKey): void
    {
        $collectionId = $this->collectionIdByKey($collectionKey);
        if ($collectionId === null) {
            return;
        }

        $items = $this->db->table('cms_menu_items')
            ->select('id')
            ->where('menu_id', $menuId)
            ->where('link_type', 'collection_listing')
            ->where('collection_id', $collectionId)
            ->get()
            ->getResultArray();

        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $this->db->table('cms_menu_item_translations')->where('menu_item_id', $itemId)->delete();
            $this->db->table('cms_menu_items')->where('id', $itemId)->delete();
        }
    }

    /** @param list<int> $ids */
    private function appendId(array &$ids, ?int $id): void
    {
        if ($id !== null) {
            $ids[] = $id;
        }
    }

    /** @param list<int> $keepIds */
    private function pruneMenuItems(int $menuId, array $keepIds): void
    {
        // Never remove menu items: custom navigation is editorial content.
        unset($menuId, $keepIds);
    }
}
