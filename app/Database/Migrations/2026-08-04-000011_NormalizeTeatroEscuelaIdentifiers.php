<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes TeatroEscuela the canonical active CMS contract.
 *
 * Legacy source tables and columns intentionally keep their historical names;
 * this migration only changes the active CMS collection/block identifiers and
 * JSON references that point at them.
 *
 * @cms-content-data-migration
 */
final class NormalizeTeatroEscuelaIdentifiers extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('cms_collections')) {
            return;
        }

        $this->renameUniqueValue('cms_collections', 'collection_key', 'cursos', 'teatroescuela');
        $this->renameUniqueValue('cms_content_blocks', 'block_key', 'curso_ficha', 'teatroescuela_ficha');
        $this->replaceJsonReferences('cms_collections', ['block_template', 'wizard_config']);
        $this->replaceJsonReferences('cms_block_instances', ['block_config']);
        $this->replaceJsonReferences('cms_page_block_instances', ['block_config']);
    }

    public function down(): void
    {
        // Forward-only. The old identifiers remain supported only as read aliases.
    }

    private function renameUniqueValue(string $table, string $column, string $old, string $new): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        $oldRow = $this->db->table($table)->where($column, $old)->get()->getRowArray();
        if ($oldRow === null) {
            return;
        }
        $newRow = $this->db->table($table)->where($column, $new)->get()->getRowArray();
        if ($newRow !== null && (int) ($newRow['id'] ?? 0) !== (int) ($oldRow['id'] ?? 0)) {
            throw new \RuntimeException(sprintf('Cannot rename %s.%s: both %s and %s exist.', $table, $column, $old, $new));
        }
        $this->db->table($table)->where('id', (int) $oldRow['id'])->update([$column => $new]);
    }

    /** @param list<string> $columns */
    private function replaceJsonReferences(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        foreach ($this->db->table($table)->get()->getResultArray() as $row) {
            $updates = [];
            foreach ($columns as $column) {
                if (! is_string($row[$column] ?? null) || trim($row[$column]) === '') {
                    continue;
                }
                $decoded = json_decode($row[$column], true);
                if (! is_array($decoded)) {
                    continue;
                }
                $normalized = $this->replaceRecursive($decoded);
                if ($normalized !== $decoded) {
                    $updates[$column] = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
            }
            if ($updates !== [] && isset($row['id'])) {
                $this->db->table($table)->where('id', (int) $row['id'])->update($updates);
            }
        }
    }

    /** @return array<string|int, mixed> */
    private function replaceRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = is_array($item) ? $this->replaceRecursive($item) : (is_string($item) ? match ($item) {
                'cursos' => 'teatroescuela', 'curso_ficha' => 'teatroescuela_ficha', default => $item,
            } : $item);
        }
        return $value;
    }
}
