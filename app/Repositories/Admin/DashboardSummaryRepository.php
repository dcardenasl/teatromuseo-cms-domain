<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Interfaces\Admin\DashboardSummaryRepositoryInterface;
use App\Models\CategoryModel;
use App\Models\CollectionModel;
use App\Models\EntryModel;
use App\Models\EntryTranslationModel;
use App\Models\FormModel;
use App\Models\FormSubmissionModel;
use App\Models\MenuModel;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Models\TagModel;

final class DashboardSummaryRepository implements DashboardSummaryRepositoryInterface
{
    public function __construct(
        private readonly PageModel $pages,
        private readonly EntryModel $entries,
        private readonly CollectionModel $collections,
        private readonly MenuModel $menus,
        private readonly CategoryModel $categories,
        private readonly TagModel $tags,
        private readonly FormModel $forms,
        private readonly FormSubmissionModel $submissions,
        private readonly PageTranslationModel $pageTranslations,
        private readonly EntryTranslationModel $entryTranslations,
    ) {
    }

    /**
     * This projection uses bounded queries and never calls the CRUD HTTP
     * surface from inside the domain.
     *
     * @return array<string, mixed>
     */
    public function read(array $permissions): array
    {
        $canReadPages = in_array('cms.pages.read', $permissions, true);
        $canReadEntries = in_array('cms.entries.read', $permissions, true);
        $pages = $canReadPages ? array_values(array_filter($this->pages
            ->select('id, updated_at')
            ->orderBy('updated_at', 'DESC')
            ->findAll(5), 'is_object')) : [];
        $entries = $canReadEntries ? array_values(array_filter($this->entries
            ->select('id, updated_at')
            ->orderBy('updated_at', 'DESC')
            ->findAll(5), 'is_object')) : [];

        $counts = [];
        $countModels = [
            'pages' => [$this->pages, 'cms.pages.read'],
            'entries' => [$this->entries, 'cms.entries.read'],
            'collections' => [$this->collections, 'cms.collections.read'],
            'menus' => [$this->menus, 'cms.menus.read'],
            'categories' => [$this->categories, 'cms.categories.read'],
            'tags' => [$this->tags, 'cms.tags.read'],
            'forms' => [$this->forms, 'cms.forms.read'],
        ];
        foreach ($countModels as $key => [$model, $permission]) {
            if (in_array($permission, $permissions, true)) {
                $counts[$key] = (int) $model->countAllResults();
            }
        }

        return [
            'counts' => $counts,
            'submissions' => in_array('cms.submissions.read', $permissions, true)
                ? $this->submissions->countByStatus()
                : [],
            'recent_activity' => ($canReadPages || $canReadEntries)
                ? $this->recentActivity($pages, $entries)
                : [],
        ];
    }

    /**
     * @param list<object> $pages
     * @param list<object> $entries
     * @return list<array<string, mixed>>
     */
    private function recentActivity(array $pages, array $entries): array
    {
        $pageIds = $this->ids($pages);
        $entryIds = $this->ids($entries);
        $pageTranslations = $this->translations($this->pageTranslations, 'page_id', $pageIds);
        $entryTranslations = $this->translations($this->entryTranslations, 'entry_id', $entryIds);
        $items = [];

        foreach ($pages as $page) {
            $id = (int) ($page->id ?? 0);
            $items[] = [
                'type' => 'page',
                'id' => $id,
                'updated_at' => (string) ($page->updated_at ?? ''),
                'translations' => $pageTranslations[$id] ?? [],
            ];
        }

        foreach ($entries as $entry) {
            $id = (int) ($entry->id ?? 0);
            $items[] = [
                'type' => 'entry',
                'id' => $id,
                'updated_at' => (string) ($entry->updated_at ?? ''),
                'translations' => $entryTranslations[$id] ?? [],
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => strcmp(
                (string) ($right['updated_at'] ?? ''),
                (string) ($left['updated_at'] ?? '')
            )
        );

        return array_slice($items, 0, 6);
    }

    /**
     * @param list<object> $entities
     * @return list<int>
     */
    private function ids(array $entities): array
    {
        return array_values(array_filter(
            array_map(static fn (object $entity): int => (int) ($entity->id ?? 0), $entities),
            static fn (int $id): bool => $id > 0
        ));
    }

    /**
     * @param PageTranslationModel|EntryTranslationModel $model
     * @param list<int> $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function translations(PageTranslationModel|EntryTranslationModel $model, string $foreignKey, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Both translation tables expose `title` and `slug`; neither exposes
        // the legacy `name` column. Keep this projection explicit and typed so
        // a schema change cannot be hidden by a fallback expression.
        $rows = $model
            ->select($foreignKey . ', language_id, title, slug')
            ->whereIn($foreignKey, $ids)
            ->findAll();
        $grouped = [];

        foreach ($rows as $row) {
            $ownerId = (int) ($row->{$foreignKey} ?? 0);
            $grouped[$ownerId][] = [
                'language_id' => (int) ($row->language_id ?? 0),
                'title' => (string) ($row->title ?? ''),
                'slug' => (string) ($row->slug ?? ''),
            ];
        }

        return $grouped;
    }
}
