<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aligns homepage hero links with the event listing slugs that are actually
 * published by the CMS. This closes the gap left when the route contract was
 * normalized before the current localized listing pages were seeded.
 *
 * @cms-content-data-migration
 */
final class AlignHomeHeroSliderWithPublishedEventSlugs extends Migration
{
    /** @var array<string, string> */
    private const EVENT_SLUGS = [
        'es' => '/cartelera',
        'en' => '/programming',
        'fr' => '/programmation',
        'pt' => '/programacao',
    ];

    public function up(): void
    {
        $home = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'home')
            ->get()
            ->getRowArray();
        $slide = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'slide_banner')
            ->get()
            ->getRowArray();
        if (! is_array($home) || ! is_array($slide)) {
            return;
        }

        $languages = [];
        foreach ($this->db->table('cms_languages')->select('id, code')->get()->getResultArray() as $language) {
            $languages[(int) $language['id']] = (string) $language['code'];
        }

        $instances = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('block_id', (int) $slide['id'])
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $home['id'])
            ->where('parent_instance_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $translations = $this->db->table('cms_block_instance_translations')
                ->where('instance_id', (int) $instance['id'])
                ->get()
                ->getResultArray();
            foreach ($translations as $translation) {
                $locale = $languages[(int) $translation['language_id']] ?? '';
                if (! isset(self::EVENT_SLUGS[$locale])) {
                    continue;
                }

                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data) || ! $this->isEventDestination((string) ($data['cta_url'] ?? ''))) {
                    continue;
                }

                $data['cta_url'] = self::EVENT_SLUGS[$locale];
                $this->db->table('cms_block_instance_translations')
                    ->where('id', (int) $translation['id'])
                    ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: the previous paths are not the published CMS slugs.
    }

    private function isEventDestination(string $url): bool
    {
        $path = trim((string) (parse_url(trim($url), PHP_URL_PATH) ?? ''), '/');

        return in_array($path, [
            'cartelera', 'events', 'programme', 'eventos',
            'programming', 'programmation', 'programacao',
        ], true);
    }
}
