<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\BlockInstanceTranslationModel;
use App\Models\LanguageModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Applies real EN/FR/PT translations for `cms_block_instance_translations`
 * rows whose `block_data` JSON was seeded with the Spanish text duplicated
 * into every language (same legacy-migration root cause as
 * BackfillEntryTranslations — see that command's docblock).
 *
 * Unlike entries, block content lives inside a per-block-type JSON blob
 * rather than flat columns, so this command merge-patches only the field
 * keys given in the manifest into the existing `block_data`, leaving every
 * other key (image references, urls, settings, etc.) untouched. Only ever
 * UPDATEs existing rows — never creates rows.
 *
 * Dry-run by default; pass --confirm to write.
 */
class BackfillBlockTranslations extends BaseCommand
{
    protected $group       = 'CMS';
    protected $name        = 'cms:backfill-block-translations';
    protected $description = 'Merge-patches real EN/FR/PT text into legacy-migration Spanish-duplicate block_data JSON.';

    protected $usage = 'cms:backfill-block-translations --file=<path> [--confirm]';

    protected $options = [
        '--file'    => 'Path to a JSON manifest: [{"instance_id": int, "translations": {"en": {"field_key": "...", ...}, "fr": {...}, "pt": {...}}}]',
        '--confirm' => 'Actually write the rows. Without it, only prints what would change.',
    ];

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

        $translationModel = model(BlockInstanceTranslationModel::class);

        $updated = 0;
        $fieldsWritten = 0;
        $skippedNoRow = 0;
        $skippedNoFields = 0;
        $skippedUnknownLanguage = 0;
        $skippedBadBlockData = 0;

        foreach ($manifest as $item) {
            if (! is_array($item)) {
                continue;
            }

            $instanceId = (int) ($item['instance_id'] ?? 0);
            $translations = $item['translations'] ?? [];
            if ($instanceId <= 0 || ! is_array($translations)) {
                continue;
            }

            foreach ($translations as $languageCode => $fields) {
                $languageCode = (string) $languageCode;
                $languageId = $languageIdsByCode[$languageCode] ?? null;
                if ($languageId === null) {
                    $skippedUnknownLanguage++;
                    CLI::write("instance {$instanceId}: unknown language code '{$languageCode}'", 'yellow');

                    continue;
                }

                if (! is_array($fields)) {
                    continue;
                }

                $payload = array_filter($fields, static fn (mixed $v): bool => is_string($v) && trim($v) !== '');
                if ($payload === []) {
                    $skippedNoFields++;

                    continue;
                }

                $row = $translationModel
                    ->where('instance_id', $instanceId)
                    ->where('language_id', $languageId)
                    ->first();

                if ($row === null) {
                    $skippedNoRow++;
                    CLI::write("instance {$instanceId} [{$languageCode}]: no existing translation row, skipped (this command never creates rows)", 'yellow');

                    continue;
                }

                $blockData = $row->block_data;
                if (is_string($blockData)) {
                    $decoded = json_decode($blockData, true);
                    $blockData = is_array($decoded) ? $decoded : null;
                } elseif (is_object($blockData)) {
                    $blockData = (array) $blockData;
                }

                if (! is_array($blockData)) {
                    $skippedBadBlockData++;
                    CLI::write("instance {$instanceId} [{$languageCode}]: block_data is not valid JSON, skipped", 'yellow');

                    continue;
                }

                $merged = array_merge($blockData, $payload);

                $fieldList = implode(', ', array_keys($payload));
                if ($confirm) {
                    $translationModel->update($row->id, ['block_data' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    CLI::write("instance {$instanceId} [{$languageCode}]: updated ({$fieldList})", 'green');
                } else {
                    CLI::write("instance {$instanceId} [{$languageCode}]: would update ({$fieldList})");
                }

                $updated++;
                $fieldsWritten += count($payload);
            }
        }

        CLI::write('');
        CLI::write(($confirm ? 'Applied' : 'Dry-run — would apply') . " {$updated} translation-row updates ({$fieldsWritten} field values).", $confirm ? 'green' : 'yellow');
        if ($skippedNoRow > 0) {
            CLI::write("Skipped (no existing row): {$skippedNoRow}", 'yellow');
        }
        if ($skippedNoFields > 0) {
            CLI::write("Skipped (no usable fields): {$skippedNoFields}", 'yellow');
        }
        if ($skippedUnknownLanguage > 0) {
            CLI::write("Skipped (unknown language code): {$skippedUnknownLanguage}", 'yellow');
        }
        if ($skippedBadBlockData > 0) {
            CLI::write("Skipped (invalid block_data JSON): {$skippedBadBlockData}", 'yellow');
        }
        if (! $confirm) {
            CLI::write('Re-run with --confirm to write.', 'yellow');
        }
    }
}
