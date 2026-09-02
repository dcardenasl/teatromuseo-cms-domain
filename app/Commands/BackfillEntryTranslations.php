<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\EntryTranslationModel;
use App\Models\LanguageModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Applies real EN/FR/PT translations for `cms_entry_translations` rows that
 * the legacy WordPress migration seeded with the Spanish text as a
 * placeholder in every language (root-caused 2026-08-02: the legacy site
 * had no multilingual plugin — there was never a real translation to
 * recover, the ETL just never got a follow-up translation pass).
 *
 * Only ever UPDATEs existing rows for known fields (title, excerpt,
 * meta_title, meta_description) — never creates rows, never touches slug or
 * any other column. Dry-run by default; pass --confirm to write.
 */
class BackfillEntryTranslations extends BaseCommand
{
    protected $group       = 'CMS';
    protected $name        = 'cms:backfill-entry-translations';
    protected $description = 'Writes real EN/FR/PT text over legacy-migration Spanish-duplicate entry translations.';

    protected $usage = 'cms:backfill-entry-translations --file=<path> [--confirm]';

    protected $options = [
        '--file'    => 'Path to a JSON manifest: [{"entry_id": int, "translations": {"en": {"title": "...", "excerpt": "..."}, "fr": {...}, "pt": {...}}}]',
        '--confirm' => 'Actually write the rows. Without it, only prints what would change.',
    ];

    private const ALLOWED_FIELDS = ['title', 'excerpt', 'meta_title', 'meta_description'];

    public function run(array $params): void
    {
        $file = CLI::getOption('file');
        if (! is_string($file) || $file === '' || ! is_file($file)) {
            CLI::error('Missing or invalid --file path.');

            return;
        }

        $confirm = (bool) CLI::getOption('confirm');

        $raw = file_get_contents($file);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($manifest)) {
            CLI::error('Manifest is not valid JSON: ' . $file);

            return;
        }

        $languageModel = model(LanguageModel::class);
        $languageIdsByCode = [];
        foreach ($languageModel->findAll() as $language) {
            $languageIdsByCode[(string) $language->code] = (int) $language->id;
        }

        $translationModel = model(EntryTranslationModel::class);

        $updated = 0;
        $skippedNoRow = 0;
        $skippedNoFields = 0;
        $skippedUnknownLanguage = 0;

        foreach ($manifest as $item) {
            if (! is_array($item)) {
                continue;
            }

            $entryId = (int) ($item['entry_id'] ?? 0);
            $translations = $item['translations'] ?? [];
            if ($entryId <= 0 || ! is_array($translations)) {
                continue;
            }

            foreach ($translations as $languageCode => $fields) {
                $languageCode = (string) $languageCode;
                $languageId = $languageIdsByCode[$languageCode] ?? null;
                if ($languageId === null) {
                    $skippedUnknownLanguage++;
                    CLI::write("entry {$entryId}: unknown language code '{$languageCode}'", 'yellow');

                    continue;
                }

                if (! is_array($fields)) {
                    continue;
                }

                $payload = array_intersect_key($fields, array_flip(self::ALLOWED_FIELDS));
                $payload = array_filter($payload, static fn (mixed $v): bool => is_string($v) && trim($v) !== '');
                if ($payload === []) {
                    $skippedNoFields++;

                    continue;
                }

                $row = $translationModel
                    ->where('entry_id', $entryId)
                    ->where('language_id', $languageId)
                    ->first();

                if ($row === null) {
                    $skippedNoRow++;
                    CLI::write("entry {$entryId} [{$languageCode}]: no existing translation row, skipped (this command never creates rows)", 'yellow');

                    continue;
                }

                $fieldList = implode(', ', array_keys($payload));
                if ($confirm) {
                    $translationModel->update($row->id, $payload);
                    CLI::write("entry {$entryId} [{$languageCode}]: updated ({$fieldList})", 'green');
                } else {
                    CLI::write("entry {$entryId} [{$languageCode}]: would update ({$fieldList})");
                }

                $updated++;
            }
        }

        CLI::write('');
        CLI::write(($confirm ? 'Applied' : 'Dry-run — would apply') . " {$updated} translation updates.", $confirm ? 'green' : 'yellow');
        if ($skippedNoRow > 0) {
            CLI::write("Skipped (no existing row): {$skippedNoRow}", 'yellow');
        }
        if ($skippedNoFields > 0) {
            CLI::write("Skipped (no usable fields): {$skippedNoFields}", 'yellow');
        }
        if ($skippedUnknownLanguage > 0) {
            CLI::write("Skipped (unknown language code): {$skippedUnknownLanguage}", 'yellow');
        }
        if (! $confirm) {
            CLI::write('Re-run with --confirm to write.', 'yellow');
        }
    }
}
