<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores homepage hero destinations using each locale's canonical public
 * path. The web app also canonicalizes legacy aliases at read time so cached
 * content remains safe during deployment, but the CMS data must be corrected
 * at the source as well.
 *
 * @cms-schema-data-migration
 */
final class NormalizeHomeHeroSliderNavigation extends Migration
{
    /** @var array<string, array<string, string>> */
    private const DESTINATIONS = [
        'contact' => [
            'es' => '/contacto',
            'en' => '/contact',
            'fr' => '/contact',
            'pt' => '/contato',
        ],
        'events' => [
            'es' => '/cartelera',
            'en' => '/events',
            'fr' => '/programme',
            'pt' => '/eventos',
        ],
        'theatre_school' => [
            'es' => '/teatroescuela',
            'en' => '/theaterschool',
            'fr' => '/theatreecole',
            'pt' => '/escola-de-teatro',
        ],
    ];

    public function up(): void
    {
        $home = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'home')
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();
        if (! is_array($home)) {
            return;
        }

        $slide = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'slide_banner')
            ->get()
            ->getRowArray();
        if (! is_array($slide)) {
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
                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data)) {
                    continue;
                }

                $destination = $this->canonicalDestination((string) ($data['cta_url'] ?? ''), $locale);
                if ($destination === null || $destination === ($data['cta_url'] ?? null)) {
                    continue;
                }

                $data['cta_url'] = $destination;
                $this->db->table('cms_block_instance_translations')
                    ->where('id', (int) $translation['id'])
                    ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only: restoring locale-incorrect aliases would reintroduce
        // broken navigation for non-Spanish homepage visitors.
    }

    private function canonicalDestination(string $url, string $locale): ?string
    {
        $path = trim((string) (parse_url(trim($url), PHP_URL_PATH) ?? ''), '/');
        if ($path === '') {
            return $url === '/' ? '/' : null;
        }

        $aliases = [
            'contacto' => 'contact', 'contact' => 'contact', 'contato' => 'contact',
            'cartelera' => 'events', 'events' => 'events', 'programme' => 'events', 'eventos' => 'events',
            'cursos' => 'theatre_school', 'teatroescuela' => 'theatre_school',
            'theaterschool' => 'theatre_school', 'theatreecole' => 'theatre_school', 'escola-de-teatro' => 'theatre_school',
        ];
        $key = $aliases[$path] ?? null;

        return $key !== null ? (self::DESTINATIONS[$key][$locale] ?? self::DESTINATIONS[$key]['es']) : null;
    }
}
