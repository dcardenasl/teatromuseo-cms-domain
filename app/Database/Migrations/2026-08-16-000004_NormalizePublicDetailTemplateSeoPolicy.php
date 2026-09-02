<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** @cms-schema-data-migration */
final class NormalizePublicDetailTemplateSeoPolicy extends Migration
{
    /** @var array<string, int> */
    private const SEO_POLICIES = [
        'catalog_item_header' => 160,
        'event_item_header' => 160,
    ];

    public function up(): void
    {
        $this->setSeoPolicies(true);
    }

    public function down(): void
    {
        $this->setSeoPolicies(false);
    }

    private function setSeoPolicies(bool $enabled): void
    {
        $query = $this->db->table('cms_content_blocks')
            ->select('id, block_key, schema_definition')
            ->whereIn('block_key', array_keys(self::SEO_POLICIES))
            ->get();
        $rows = $query !== false ? $query->getResultArray() : [];

        foreach ($rows as $row) {
            try {
                $schema = is_string($row['schema_definition'] ?? null)
                    ? json_decode((string) $row['schema_definition'], true, 512, JSON_THROW_ON_ERROR)
                    : $row['schema_definition'];
            } catch (\JsonException) {
                continue;
            }
            if (! is_array($schema)) {
                continue;
            }

            $presentation = is_array($schema['presentation'] ?? null) ? $schema['presentation'] : [];
            $seo = is_array($presentation['seo'] ?? null) ? $presentation['seo'] : [];
            if ($enabled) {
                $seo['description_max_length'] = self::SEO_POLICIES[(string) ($row['block_key'] ?? '')];
                $presentation['seo'] = $seo;
                $schema['presentation'] = $presentation;
            } else {
                unset($seo['description_max_length']);
                if ($seo === []) {
                    unset($presentation['seo']);
                } else {
                    $presentation['seo'] = $seo;
                }
                if ($presentation === []) {
                    unset($schema['presentation']);
                } else {
                    $schema['presentation'] = $presentation;
                }
            }

            try {
                $encoded = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $this->db->table('cms_content_blocks')
                ->where('id', (int) ($row['id'] ?? 0))
                ->update(['schema_definition' => $encoded]);
        }
    }
}
