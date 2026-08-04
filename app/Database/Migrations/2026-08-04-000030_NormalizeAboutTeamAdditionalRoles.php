<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Removes profession/position duplicates from the additional roles field. */
final class NormalizeAboutTeamAdditionalRoles extends Migration
{
    public function up(): void
    {
        $rows = $this->db->table('cms_block_instance_translations t')
            ->select('t.id, t.block_data')
            ->join('cms_block_instances i', 'i.id = t.instance_id')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('b.block_key', 'team_member')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $data = json_decode((string) ($row['block_data'] ?? '{}'), true);
            if (! is_array($data)) {
                continue;
            }

            $excluded = array_filter([
                trim((string) ($data['position'] ?? '')),
                trim((string) ($data['profession'] ?? '')),
            ]);
            $excludedKeys = array_map([$this, 'normalize'], $excluded);
            $seen = [];
            $roles = [];

            foreach (is_array($data['roles'] ?? null) ? $data['roles'] : [] as $role) {
                $label = is_array($role)
                    ? trim((string) ($role['label'] ?? $role['name'] ?? ''))
                    : (is_scalar($role) ? trim((string) $role) : '');
                $key = $this->normalize($label);
                if ($label === '' || in_array($key, $excludedKeys, true) || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $roles[] = ['label' => $label];
            }

            $data['roles'] = $roles;
            $this->db->table('cms_block_instance_translations')
                ->where('id', (int) ($row['id'] ?? 0))
                ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        // Do not reintroduce duplicate data on rollback.
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
