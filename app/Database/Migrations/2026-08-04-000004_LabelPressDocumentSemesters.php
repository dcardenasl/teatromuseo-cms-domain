<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores the editorial semester on each press document block.
 *
 * @cms-content-data-migration
 */
final class LabelPressDocumentSemesters extends Migration
{
    public function up(): void
    {
        $entries = $this->db->table('cms_entries e')
            ->select('e.id, et.title')
            ->join('cms_collections c', 'c.id = e.collection_id AND c.collection_key = \'prensa\'')
            ->join('cms_entry_translations et', 'et.entry_id = e.id')
            ->join('cms_languages l', 'l.id = et.language_id AND l.code = \'es\'')
            ->get()
            ->getResultArray();

        foreach ($entries as $entry) {
            $year = $this->yearFromTitle((string) ($entry['title'] ?? ''));
            if ($year === '') {
                continue;
            }

            $blocks = $this->db->table('cms_block_instances bi')
                ->select('bi.id, bi.block_config')
                ->join('cms_content_blocks cb', 'cb.id = bi.block_id AND cb.block_key = \'document_download\'')
                ->where(['bi.owner_type' => 'entry', 'bi.owner_id' => (int) $entry['id']])
                ->orderBy('bi.id', 'ASC')
                ->get()
                ->getResultArray();

            $fileIds = [];
            foreach ($blocks as $block) {
                $config = json_decode((string) ($block['block_config'] ?? '{}'), true);
                $fileId = is_array($config) && is_array($config['document'] ?? null)
                    ? (int) ($config['document']['file_id'] ?? 0)
                    : 0;
                if ($fileId > 0) {
                    $fileIds[(int) $block['id']] = $fileId;
                }
            }

            if (count($fileIds) !== 2) {
                continue;
            }

            // The imported file sequence identifies the first and second
            // semester pairs. Persist the result in editorial data so future
            // rendering never depends on insertion order or technical IDs.
            $orderedFileIds = $this->firstSemesterFileIds($year, $fileIds);
            foreach ($fileIds as $blockId => $fileId) {
                $semester = $fileId === $orderedFileIds[0] ? 1 : 2;
                $this->updateTranslations((int) $blockId, $semester);
            }
        }
    }

    public function down(): void
    {
        // Keep the semantic labels; reverting them would reintroduce the
        // ambiguous year-only document titles.
    }

    private function updateTranslations(int $instanceId, int $semester): void
    {
        $labels = [
            'es' => $semester === 1 ? 'Primer semestre' : 'Segundo semestre',
            'en' => $semester === 1 ? 'First semester' : 'Second semester',
            'fr' => $semester === 1 ? 'Premier semestre' : 'Deuxième semestre',
            'pt' => $semester === 1 ? 'Primeiro semestre' : 'Segundo semestre',
        ];

        $rows = $this->db->table('cms_block_instance_translations bit')
            ->select('bit.id, bit.block_data, l.code')
            ->join('cms_languages l', 'l.id = bit.language_id')
            ->where('bit.instance_id', $instanceId)
            ->get()
            ->getResultArray();
        foreach ($rows as $row) {
            $data = json_decode((string) ($row['block_data'] ?? '{}'), true);
            $data = is_array($data) ? $data : [];
            $data['title'] = $labels[(string) ($row['code'] ?? '')] ?? $labels['es'];
            $this->db->table('cms_block_instance_translations')
                ->where('id', (int) $row['id'])
                ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    /** @param array<int, int> $blockFileIds @return array{0: int, 1: int} */
    private function firstSemesterFileIds(string $year, array $blockFileIds): array
    {
        $fileIds = array_values($blockFileIds);
        $knownPairs = [
            '2016' => [139, 102],
            '2017' => [103, 104],
            '2018' => [140, 105],
            '2019' => [141, 106],
        ];
        if (isset($knownPairs[$year]) && count(array_diff($knownPairs[$year], $fileIds)) === 0) {
            return $knownPairs[$year];
        }

        sort($fileIds, SORT_NUMERIC);

        return [$fileIds[0], $fileIds[1]];
    }

    private function yearFromTitle(string $title): string
    {
        return preg_match('/\b(?:19|20)\d{2}\b/', $title, $match) === 1 ? $match[0] : '';
    }
}
