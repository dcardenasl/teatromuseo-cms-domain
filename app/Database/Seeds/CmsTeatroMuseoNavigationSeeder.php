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
        if (! isset($languages['es'], $languages['en'])) {
            echo "CmsTeatroMuseoNavigationSeeder: missing languages; skipping.\n";

            return;
        }

        $homePageId = $this->pageIdByType('home');
        $contactPageId = $this->pageIdByType('contact');

        if ($homePageId === null || $contactPageId === null) {
            echo "CmsTeatroMuseoNavigationSeeder: missing home or contact page; skipping.\n";

            return;
        }

        $this->seedMainMenu($languages, $homePageId, $contactPageId);
        $this->seedFooterMenu($languages, $homePageId, $contactPageId);
    }

    /** @param array<string, int> $languages */
    private function seedMainMenu(array $languages, int $homePageId, int $contactPageId): void
    {
        $menuId = $this->upsertMenu('main', 'header', [
            'es' => 'Navegación principal',
            'en' => 'Main navigation',
        ], $languages);
        $keepIds = [];

        $homeId = $this->addPageItem($menuId, $homePageId, null, 1, [
            'es' => 'Inicio',
            'en' => 'Home',
        ], $languages);
        $this->appendId($keepIds, $homeId);

        $keepIds = array_merge($keepIds, $this->addGroup($menuId, 2, [
            ['collection_key' => 'obras', 'sort_order' => 1, 'es' => 'Obras', 'en' => 'Works'],
            ['collection_key' => 'companias', 'sort_order' => 2, 'es' => 'Compañías', 'en' => 'Companies'],
        ], ['es' => 'Cartelera', 'en' => 'Programación'], $languages));

        $festivalsId = $this->addCollectionItem($menuId, 'festivales', null, 3, [
            'es' => 'Festivales',
            'en' => 'Festivals',
        ], $languages);
        $this->appendId($keepIds, $festivalsId);

        $keepIds = array_merge($keepIds, $this->addGroup($menuId, 4, [
            ['collection_key' => 'exposiciones', 'sort_order' => 1, 'es' => 'Exposiciones', 'en' => 'Exhibitions'],
            ['collection_key' => 'personas', 'sort_order' => 2, 'es' => 'Personas', 'en' => 'People'],
        ], ['es' => 'Museo', 'en' => 'Museum'], $languages));

        $coursesId = $this->addCollectionItem($menuId, 'cursos', null, 5, [
            'es' => 'Educación',
            'en' => 'Education',
        ], $languages);
        $this->appendId($keepIds, $coursesId);

        $videosId = $this->addCollectionItem($menuId, 'videos', null, 6, [
            'es' => 'Multimedia',
            'en' => 'Media',
        ], $languages);
        $this->appendId($keepIds, $videosId);

        $publicationsId = $this->addCollectionItem($menuId, 'publicaciones', null, 7, [
            'es' => 'Prensa',
            'en' => 'Press',
        ], $languages);
        $this->appendId($keepIds, $publicationsId);

        $newsId = $this->addCollectionItem($menuId, 'noticias', null, 8, [
            'es' => 'Noticias',
            'en' => 'News',
        ], $languages);
        $this->appendId($keepIds, $newsId);

        $contactId = $this->addPageItem($menuId, $contactPageId, null, 9, [
            'es' => 'Contacto',
            'en' => 'Contact',
        ], $languages);
        $this->appendId($keepIds, $contactId);

        $this->pruneMenuItems($menuId, $keepIds);
    }

    /** @param array<string, int> $languages */
    private function seedFooterMenu(array $languages, int $homePageId, int $contactPageId): void
    {
        $menuId = $this->upsertMenu('footer', 'footer', [
            'es' => 'Pie de página',
            'en' => 'Footer navigation',
        ], $languages);
        $keepIds = [];

        $homeId = $this->addPageItem($menuId, $homePageId, null, 1, [
            'es' => 'Inicio',
            'en' => 'Home',
        ], $languages);
        $this->appendId($keepIds, $homeId);

        $footerCollections = [
            ['collection_key' => 'obras', 'es' => 'Obras', 'en' => 'Works'],
            ['collection_key' => 'festivales', 'es' => 'Festivales', 'en' => 'Festivals'],
            ['collection_key' => 'exposiciones', 'es' => 'Exposiciones', 'en' => 'Exhibitions'],
            ['collection_key' => 'cursos', 'es' => 'Educación', 'en' => 'Education'],
            ['collection_key' => 'videos', 'es' => 'Multimedia', 'en' => 'Media'],
            ['collection_key' => 'publicaciones', 'es' => 'Prensa', 'en' => 'Press'],
            ['collection_key' => 'noticias', 'es' => 'Noticias', 'en' => 'News'],
        ];

        $sortOrder = 2;
        foreach ($footerCollections as $definition) {
            $itemId = $this->addCollectionItem(
                $menuId,
                $definition['collection_key'],
                null,
                $sortOrder,
                ['es' => $definition['es'], 'en' => $definition['en']],
                $languages
            );
            $this->appendId($keepIds, $itemId);
            $sortOrder++;
        }

        $contactId = $this->addPageItem($menuId, $contactPageId, null, $sortOrder, [
            'es' => 'Contacto',
            'en' => 'Contact',
        ], $languages);
        $this->appendId($keepIds, $contactId);

        $this->pruneMenuItems($menuId, $keepIds);
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

    /** @param array{es: string, en: string} $translations @param array<string, int> $languages */
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
     * @param array{es: string, en: string} $translations
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

    /** @param array{es: string, en: string} $translations @param array<string, int> $languages */
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

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', ['es', 'en'])
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
