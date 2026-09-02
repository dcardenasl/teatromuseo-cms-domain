<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** @cms-schema-data-migration */
final class NormalizePublicHeadingBlockCapabilities extends Migration
{
    /** @var list<string> */
    private const HEADING_BLOCK_KEYS = [
        'hero_slider',
        'hero_banner',
        'page_header',
        'catalog_item_header',
        'event_item_header',
    ];

    public function up(): void
    {
        $this->setHeadingCapability(true);
    }

    public function down(): void
    {
        $this->setHeadingCapability(false);
    }

    private function setHeadingCapability(bool $enabled): void
    {
        $query = $this->db->table('cms_content_blocks')
            ->select('id, schema_definition')
            ->whereIn('block_key', self::HEADING_BLOCK_KEYS)
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
            if ($enabled) {
                $presentation['owns_page_heading'] = true;
                $schema['presentation'] = $presentation;
            } else {
                unset($presentation['owns_page_heading']);
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
