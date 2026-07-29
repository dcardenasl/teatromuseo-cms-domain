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
        $eventsPageId = $this->pageIdByType('events') ?? $this->pageIdBySlug(['cartelera']);
        $catalogListingPageId = $this->pageIdByType('catalog_listing') ?? $this->pageIdBySlug(['museo/coleccion']);

        if ($homePageId === null || $contactPageId === null) {
            echo "CmsTeatroMuseoNavigationSeeder: missing home or contact page; skipping.\n";

            return;
        }

        $this->seedMainMenu($languages, $homePageId, $contactPageId, $aboutPageId, $historyPageId, $eventsPageId, $catalogListingPageId);
        $this->seedFooterMenu($languages, $homePageId, $contactPageId, $aboutPageId, $historyPageId, $eventsPageId);
    }

    /** @param array<string, int> $languages */
    private function seedMainMenu(
        array $languages,
        int $homePageId,
        int $contactPageId,
        ?int $aboutPageId,
        ?int $historyPageId,
        ?int $eventsPageId,
        ?int $catalogListingPageId
    ): void {
        $menuId = $this->upsertMenu('main', 'header', [
            'es' => 'Navegación principal',
            'en' => 'Main navigation',
            'fr' => 'Navigation principale',
            'pt' => 'Navegação principal',
        ], $languages);
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
        ], $languages));

        $carteleraId = $eventsPageId !== null
            ? $this->addPageItem($menuId, $eventsPageId, null, $sortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Programming',
                'fr' => 'Programmation',
                'pt' => 'Programação',
            ], $languages)
            : $this->addEventListingItem($menuId, null, $sortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Programming',
                'fr' => 'Programmation',
                'pt' => 'Programação',
            ], $languages);
        $this->appendId($keepIds, $carteleraId);

        if ($carteleraId !== null) {
            $companiasId = $this->addCollectionItem($menuId, 'companias', $carteleraId, 1, [
                'es' => 'Compañías',
                'en' => 'Companies',
                'fr' => 'Compagnies',
                'pt' => 'Companhias',
            ], $languages);
            $this->appendId($keepIds, $companiasId);
        }

        $festivalsId = $this->addCollectionItem($menuId, 'festivales', null, $sortOrder++, [
            'es' => 'Festivales',
            'en' => 'Festivals',
            'fr' => 'Festivals',
            'pt' => 'Festivais',
        ], $languages);
        $this->appendId($keepIds, $festivalsId);

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

        $coursesId = $this->addCollectionItem($menuId, 'cursos', null, $sortOrder++, [
            'es' => 'Educación',
            'en' => 'Education',
            'fr' => 'Éducation',
            'pt' => 'Educação',
        ], $languages);
        $this->appendId($keepIds, $coursesId);

        $videosId = $this->addCollectionItem($menuId, 'videos', null, $sortOrder++, [
            'es' => 'Multimedia',
            'en' => 'Media',
            'fr' => 'Médias',
            'pt' => 'Mídia',
        ], $languages);
        $this->appendId($keepIds, $videosId);

        $publicationsId = $this->addCollectionItem($menuId, 'publicaciones', null, $sortOrder++, [
            'es' => 'Prensa',
            'en' => 'Press',
            'fr' => 'Presse',
            'pt' => 'Imprensa',
        ], $languages);
        $this->appendId($keepIds, $publicationsId);

        $newsId = $this->addCollectionItem($menuId, 'noticias', null, $sortOrder++, [
            'es' => 'Noticias',
            'en' => 'News',
            'fr' => 'Actualités',
            'pt' => 'Notícias',
        ], $languages);
        $this->appendId($keepIds, $newsId);

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
        ?int $eventsPageId
    ): void {
        $menuId = $this->upsertMenu('footer', 'footer', [
            'es' => 'Pie de página',
            'en' => 'Footer navigation',
            'fr' => 'Navigation de pied de page',
            'pt' => 'Navegação de rodapé',
        ], $languages);
        $keepIds = [];
        $sortOrder = 1;

        $homeId = $this->addPageItem($menuId, $homePageId, null, $sortOrder++, [
            'es' => 'Inicio',
            'en' => 'Home',
            'fr' => 'Accueil',
            'pt' => 'Início',
        ], $languages);
        $this->appendId($keepIds, $homeId);

        if ($aboutPageId !== null) {
            $aboutId = $this->addPageItem($menuId, $aboutPageId, null, $sortOrder++, [
                'es' => 'Quiénes Somos',
                'en' => 'About Us',
                'fr' => 'À propos',
                'pt' => 'Sobre Nós',
            ], $languages);
            $this->appendId($keepIds, $aboutId);
        }

        if ($historyPageId !== null) {
            $historyId = $this->addPageItem($menuId, $historyPageId, null, $sortOrder++, [
                'es' => 'Historia',
                'en' => 'History',
                'fr' => 'Histoire',
                'pt' => 'História',
            ], $languages);
            $this->appendId($keepIds, $historyId);
        }

        $carteleraId = $eventsPageId !== null
            ? $this->addPageItem($menuId, $eventsPageId, null, $sortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Programming',
                'fr' => 'Programmation',
                'pt' => 'Programação',
            ], $languages)
            : $this->addEventListingItem($menuId, null, $sortOrder++, [
                'es' => 'Cartelera',
                'en' => 'Programming',
                'fr' => 'Programmation',
                'pt' => 'Programação',
            ], $languages);
        $this->appendId($keepIds, $carteleraId);

        foreach ([
            ['collection_key' => 'festivales', 'es' => 'Festivales', 'en' => 'Festivals', 'fr' => 'Festivals', 'pt' => 'Festivais'],
            ['collection_key' => 'exposiciones', 'es' => 'Exposiciones', 'en' => 'Exhibitions', 'fr' => 'Expositions', 'pt' => 'Exposições'],
            ['collection_key' => 'cursos', 'es' => 'Educación', 'en' => 'Education', 'fr' => 'Éducation', 'pt' => 'Educação'],
            ['collection_key' => 'videos', 'es' => 'Multimedia', 'en' => 'Media', 'fr' => 'Médias', 'pt' => 'Mídia'],
            ['collection_key' => 'publicaciones', 'es' => 'Prensa', 'en' => 'Press', 'fr' => 'Presse', 'pt' => 'Imprensa'],
            ['collection_key' => 'noticias', 'es' => 'Noticias', 'en' => 'News', 'fr' => 'Actualités', 'pt' => 'Notícias'],
        ] as $definition) {
            $itemId = $this->addCollectionItem(
                $menuId,
                $definition['collection_key'],
                null,
                $sortOrder,
                [
                    'es' => $definition['es'],
                    'en' => $definition['en'],
                    'fr' => $definition['fr'],
                    'pt' => $definition['pt'],
                ],
                $languages
            );
            $this->appendId($keepIds, $itemId);
            $sortOrder++;
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
        $keepIds = array_values(array_unique(array_filter(
            array_map(static fn (int $id): int => $id, $keepIds),
            static fn (int $id): bool => $id > 0
        )));
        $builder = $this->db->table('cms_menu_items')->where('menu_id', $menuId);

        if ($keepIds !== []) {
            $builder->whereNotIn('id', $keepIds);
        }

        $builder->delete();
    }
}
