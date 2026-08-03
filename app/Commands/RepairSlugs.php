<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Cms\JsonCastNormalizer;
use App\Libraries\Cms\SlugGenerator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

final class RepairSlugs extends BaseCommand
{
    protected $group = 'CMS';
    protected $name = 'cms:repair-slugs';
    protected $description = 'Rebuilds CMS slugs from source text and rewrites them to the canonical title-derived value.';
    protected $usage = 'cms:repair-slugs [--confirm]';

    protected $options = [
        '--confirm' => 'Write the repaired slugs. Without it, the command only prints the planned changes.',
    ];

    public function run(array $params): void
    {
        $confirm = (bool) CLI::getOption('confirm');
        $db = Database::connect();
        $generator = new SlugGenerator();

        $pageRows = $this->loadPageRows($db);
        $entryRows = $this->loadEntryRows($db, $generator);
        $collectionRows = $this->loadTranslationRows($db, 'cms_collection_translations', 'collection_id', 'name');
        $categoryRows = $this->loadTranslationRows($db, 'cms_category_translations', 'category_id', 'name');
        $tagRows = $this->loadTranslationRows($db, 'cms_tag_translations', 'tag_id', 'name');

        $pagePlan = $this->buildLocalizedSlugPlan($pageRows, 'page_id', 'title', $generator);
        $entryPlan = $this->buildLocalizedSlugPlan($entryRows, 'entry_id', 'source_value', $generator);
        $collectionPlan = $this->buildLocalizedSlugPlan($collectionRows, 'collection_id', 'source_value', $generator);
        $categoryPlan = $this->buildLocalizedSlugPlan($categoryRows, 'category_id', 'source_value', $generator);
        $tagPlan = $this->buildLocalizedSlugPlan($tagRows, 'tag_id', 'source_value', $generator);

        $changes = array_merge(
            $pagePlan['changes'],
            $entryPlan['changes'],
            $collectionPlan['changes'],
            $categoryPlan['changes'],
            $tagPlan['changes']
        );

        if ($changes === []) {
            CLI::write('No CMS slugs need repair.', 'green');

            return;
        }

        CLI::write($confirm ? 'Applying CMS slug repair...' : 'Dry-run CMS slug repair...');
        CLI::write('Pages changes: ' . count($pagePlan['changes']));
        CLI::write('Entries changes: ' . count($entryPlan['changes']));
        CLI::write('Collections changes: ' . count($collectionPlan['changes']));
        CLI::write('Categories changes: ' . count($categoryPlan['changes']));
        CLI::write('Tags changes: ' . count($tagPlan['changes']));

        if (! $confirm) {
            CLI::write('Re-run with --confirm to write the repaired slugs.', 'yellow');

            return;
        }

        $db->transStart();

        $this->applySlugChanges($db, 'cms_page_translations', $pagePlan['changes'], true);
        $this->applySlugChanges($db, 'cms_entry_translations', $entryPlan['changes'], true);
        $this->applySlugChanges($db, 'cms_collection_translations', $collectionPlan['changes'], false);
        $this->applySlugChanges($db, 'cms_category_translations', $categoryPlan['changes'], false);
        $this->applySlugChanges($db, 'cms_tag_translations', $tagPlan['changes'], false);

        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Could not persist the repaired CMS slugs.');

            return;
        }

        CLI::write('CMS slug repair completed successfully.', 'green');
    }

    /**
     * @return list<array<string, mixed>>
     * @param BaseConnection<object, object> $db
     */
    private function loadPageRows(BaseConnection $db): array
    {
        $query = $db->table('cms_page_translations pt')
            ->select('pt.id, pt.page_id, pt.language_id, pt.slug, pt.title, p.parent_id')
            ->join('cms_pages p', 'p.id = pt.page_id')
            ->orderBy('pt.language_id', 'ASC')
            ->orderBy('pt.page_id', 'ASC')
            ->orderBy('pt.id', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_values(array_filter($rows, static fn (array $row): bool => isset($row['id'], $row['page_id'], $row['language_id'])));
    }

    /**
     * @return list<array<string, mixed>>
     * @param BaseConnection<object, object> $db
     */
    private function loadEntryRows(BaseConnection $db, SlugGenerator $generator): array
    {
        $query = $db->table('cms_entry_translations et')
            ->select('et.id, et.entry_id, et.language_id, et.slug, et.title, c.collection_key')
            ->join('cms_entries e', 'e.id = et.entry_id')
            ->join('cms_collections c', 'c.id = e.collection_id')
            ->orderBy('et.language_id', 'ASC')
            ->orderBy('et.entry_id', 'ASC')
            ->orderBy('et.id', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        $courseYears = $this->loadCourseStartYears($db);

        foreach ($rows as &$row) {
            $title = trim((string) ($row['title'] ?? ''));
            $collectionKey = (string) ($row['collection_key'] ?? '');
            $entryId = (int) ($row['entry_id'] ?? 0);

            if ($collectionKey === 'cursos') {
                $year = $courseYears[$entryId] ?? '';
                $row['source_value'] = $this->courseSourceValue($title, $year, $generator);
                continue;
            }

            $row['source_value'] = $title;
        }
        unset($row);

        return array_values(array_filter($rows, static fn (array $row): bool => isset($row['id'], $row['entry_id'], $row['language_id'])));
    }

    /**
     * @return list<array<string, mixed>>
     * @param BaseConnection<object, object> $db
     */
    private function loadTranslationRows(BaseConnection $db, string $table, string $resourceIdColumn, string $sourceColumn): array
    {
        $query = $db->table($table)
            ->select('id, ' . $resourceIdColumn . ' AS resource_id, language_id, slug, ' . $sourceColumn . ' AS source_value')
            ->orderBy('language_id', 'ASC')
            ->orderBy($resourceIdColumn, 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_values(array_filter($rows, static fn (array $row): bool => isset($row['id'], $row['resource_id'], $row['language_id'])));
    }

    /**
     * @return array<int, string>
     * @param BaseConnection<object, object> $db
     */
    private function loadCourseStartYears(BaseConnection $db): array
    {
        $query = $db->table('cms_block_instance_translations bt')
            ->select('bi.owner_id AS entry_id, bt.block_data')
            ->join('cms_block_instances bi', 'bi.id = bt.instance_id')
            ->join('cms_content_blocks cb', 'cb.id = bi.block_id')
            ->where('bi.owner_type', 'entry')
            ->where('cb.block_key', 'curso_ficha')
            ->orderBy('bi.owner_id', 'ASC')
            ->orderBy('bt.language_id', 'ASC')
            ->orderBy('bt.id', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        $years = [];
        foreach ($rows as $row) {
            $entryId = (int) ($row['entry_id'] ?? 0);
            if ($entryId <= 0 || isset($years[$entryId])) {
                continue;
            }

            $blockData = JsonCastNormalizer::toArray($row['block_data'] ?? null);
            $startDate = trim((string) ($blockData['start_date'] ?? ''));
            if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 1) {
                $years[$entryId] = substr($startDate, 0, 4);
            }
        }

        return $years;
    }

    private function courseSourceValue(string $title, string $year, SlugGenerator $generator): string
    {
        $title = trim($title);
        if ($title === '' || $year === '') {
            return $title;
        }

        $titleSlug = $generator->slugify($title);
        if ($titleSlug !== '' && preg_match('/(?:^|-)'.preg_quote($year, '/').'$/', $titleSlug) === 1) {
            return $title;
        }

        return trim($title . ' ' . $year);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{changes: list<array<string, mixed>>, current: array<int, array<int, string>>, planned: array<int, array<int, string>>}
     */
    private function buildLocalizedSlugPlan(array $rows, string $resourceIdKey, string $sourceKey, SlugGenerator $generator): array
    {
        $current = [];
        $rowsByLanguage = [];

        foreach ($rows as $row) {
            $resourceId = (int) ($row[$resourceIdKey] ?? 0);
            $languageId = (int) ($row['language_id'] ?? 0);
            $slug = trim((string) ($row['slug'] ?? ''));

            if ($resourceId > 0 && $languageId > 0) {
                $rowsByLanguage[$languageId][] = $row;
                $current[$resourceId][$languageId] = $slug;
            }
        }

        $changes = [];
        $planned = $current;

        foreach ($rowsByLanguage as $languageId => $languageRows) {
            usort(
                $languageRows,
                static fn (array $left, array $right): int => ((int) ($left[$resourceIdKey] ?? 0) <=> (int) ($right[$resourceIdKey] ?? 0))
                    ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0))
            );

            $taken = [];
            foreach ($languageRows as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug !== '') {
                    $taken[$slug] = true;
                }
            }

            foreach ($languageRows as $row) {
                $resourceId = (int) ($row[$resourceIdKey] ?? 0);
                $translationId = (int) ($row['id'] ?? 0);
                $currentSlug = trim((string) ($row['slug'] ?? ''));
                $sourceValue = trim((string) ($row[$sourceKey] ?? ''));

                if ($resourceId <= 0 || $translationId <= 0) {
                    continue;
                }

                if ($currentSlug !== '') {
                    unset($taken[$currentSlug]);
                }

                if ($sourceValue === '') {
                    if ($currentSlug !== '') {
                        $taken[$currentSlug] = true;
                    }

                    $planned[$resourceId][$languageId] = $currentSlug;

                    continue;
                }

                $baseSlug = $generator->slugify($sourceValue);
                if ($baseSlug === '') {
                    if ($currentSlug !== '') {
                        $taken[$currentSlug] = true;
                    }

                    $planned[$resourceId][$languageId] = $currentSlug;

                    continue;
                }

                $finalSlug = $generator->uniquify(
                    $baseSlug,
                    static fn (string $candidate): bool => ! isset($taken[$candidate])
                );
                $taken[$finalSlug] = true;
                $planned[$resourceId][$languageId] = $finalSlug;

                if ($currentSlug !== $finalSlug) {
                    $changes[] = [
                        'id'          => $translationId,
                        'resource_id' => $resourceId,
                        'language_id' => $languageId,
                        'old_slug'    => $currentSlug,
                        'new_slug'    => $finalSlug,
                    ];
                }
            }
        }

        return [
            'changes' => $changes,
            'current' => $current,
            'planned' => $planned,
        ];
    }

    /**
     * @param list<array<string, mixed>> $changes
     * @param BaseConnection<object, object> $db
     */
    private function applySlugChanges(BaseConnection $db, string $table, array $changes, bool $updateTimestamps): void
    {
        if ($changes === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($changes as $change) {
            $payload = [
                'slug' => $change['new_slug'],
            ];
            if ($updateTimestamps) {
                $payload['updated_at'] = $now;
            }

            $db->table($table)
                ->where('id', (int) $change['id'])
                ->update($payload);
        }
    }

}
