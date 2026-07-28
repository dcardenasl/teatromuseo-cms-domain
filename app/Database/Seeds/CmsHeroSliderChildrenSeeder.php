<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Seeds slide_banner children for the homepage hero_slider.
 * Idempotent: upserts children by parent_instance_id + sort_order.
 *
 * Depends on CmsBlockTypeSeeder (block types) and CmsPageBlockSeeder
 * (which creates the hero_slider instance on the home page).
 */
class CmsHeroSliderChildrenSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
        if (! isset($langIds['es'], $langIds['en'], $langIds['fr'], $langIds['pt'])) {
            echo "CmsHeroSliderChildrenSeeder: missing languages. Seed CmsLanguageSeeder first.\n";
            return;
        }

        $slideBannerId = $this->blockId('slide_banner');
        if ($slideBannerId === null) {
            echo "CmsHeroSliderChildrenSeeder: slide_banner block type not found.\n";
            return;
        }

        $heroSliderInstanceId = $this->heroSliderInstanceId();
        if ($heroSliderInstanceId === null) {
            echo "CmsHeroSliderChildrenSeeder: hero_slider instance on home page not found.\n";
            return;
        }

        $this->resetHeroSliderChildren($heroSliderInstanceId);

        $homePageId = $this->homePageId();
        if ($homePageId === null) {
            echo "CmsHeroSliderChildrenSeeder: home page not found.\n";
            return;
        }

        $slides = [
            [
                'sort_order' => 1,
                'image'      => $this->mediaReference('https://picsum.photos/id/180/1920/1080'),
                'data' => [
                    'es' => [
                        'heading'   => 'Bienvenidos a TeatroMuseo',
                        'subtitle'  => 'Contenido multilingüe y gestión moderna para TeatroMuseo.',
                        'cta_label' => 'Conocer más',
                        'cta_url'   => '/contacto',
                    ],
                    'en' => [
                        'heading'   => 'Welcome to TeatroMuseo',
                        'subtitle'  => 'Multilingual content and modern management for TeatroMuseo.',
                        'cta_label' => 'Learn more',
                        'cta_url'   => '/contact',
                    ],
                    'fr' => [
                        'heading'   => 'Bienvenue à TeatroMuseo',
                        'subtitle'  => 'Contenu multilingue et gestion moderne pour TeatroMuseo.',
                        'cta_label' => 'En savoir plus',
                        'cta_url'   => '/contact',
                    ],
                    'pt' => [
                        'heading'   => 'Bem-vindo ao TeatroMuseo',
                        'subtitle'  => 'Conteúdo multilíngue e gestão moderna para o TeatroMuseo.',
                        'cta_label' => 'Saiba mais',
                        'cta_url'   => '/contato',
                    ],
                ],
            ],
            [
                'sort_order' => 2,
                'image'      => $this->mediaReference('https://picsum.photos/id/24/1920/1080'),
                'data' => [
                    'es' => [
                        'heading'   => 'Exposiciones y memoria',
                        'subtitle'  => 'Recorre la colección de exposiciones, montajes y piezas que dan forma a TeatroMuseo.',
                        'cta_label' => 'Ver exposiciones',
                        'cta_url'   => '/exposiciones',
                    ],
                    'en' => [
                        'heading'   => 'Exhibitions and memory',
                        'subtitle'  => 'Browse the exhibitions, stagings, and pieces that shape TeatroMuseo.',
                        'cta_label' => 'View exhibitions',
                        'cta_url'   => '/exhibitions',
                    ],
                    'fr' => [
                        'heading'   => 'Expositions et mémoire',
                        'subtitle'  => 'Parcourez les expositions, mises en scène et pièces qui façonnent TeatroMuseo.',
                        'cta_label' => 'Voir les expositions',
                        'cta_url'   => '/expositions',
                    ],
                    'pt' => [
                        'heading'   => 'Exposições e memória',
                        'subtitle'  => 'Percorra as exposições, encenações e peças que dão forma ao TeatroMuseo.',
                        'cta_label' => 'Ver exposições',
                        'cta_url'   => '/exposicoes',
                    ],
                ],
            ],
            [
                'sort_order' => 3,
                'image'      => $this->mediaReference('https://picsum.photos/id/370/1920/1080'),
                'data' => [
                    'es' => [
                        'heading'   => 'Contáctanos',
                        'subtitle'  => 'Escríbenos a TeatroMuseo y te responderemos a la brevedad.',
                        'cta_label' => 'Ir al formulario',
                        'cta_url'   => '/contacto',
                    ],
                    'en' => [
                        'heading'   => 'Contact Us',
                        'subtitle'  => 'Write to TeatroMuseo and we will reply as soon as possible.',
                        'cta_label' => 'Open form',
                        'cta_url'   => '/contact',
                    ],
                    'fr' => [
                        'heading'   => 'Contactez-nous',
                        'subtitle'  => 'Écrivez à TeatroMuseo et nous vous répondrons dès que possible.',
                        'cta_label' => 'Ouvrir le formulaire',
                        'cta_url'   => '/contact',
                    ],
                    'pt' => [
                        'heading'   => 'Fale conosco',
                        'subtitle'  => 'Escreva para o TeatroMuseo e responderemos o mais breve possível.',
                        'cta_label' => 'Abrir formulário',
                        'cta_url'   => '/contato',
                    ],
                ],
            ],
        ];

        foreach ($slides as $slide) {
            $instanceId = $this->upsertRecord('cms_block_instances', [
                'block_id'           => $slideBannerId,
                'owner_type'         => 'page',
                'owner_id'           => $homePageId,
                'parent_instance_id' => $heroSliderInstanceId,
                'sort_order'         => (int) $slide['sort_order'],
            ], [
                'column_index' => null,
                'is_active'    => 1,
                'block_config' => json_encode([
                    'image'         => $slide['image'],
                    'text_color' => '#ffffff',
                    'overlay_color' => 'rgba(15, 23, 42, 0.4)',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if ($instanceId === null) {
                continue;
            }

            foreach ($slide['data'] as $langCode => $data) {
                $langId = $langIds[$langCode] ?? null;
                if ($langId === null) {
                    continue;
                }
                $this->upsertTranslation($instanceId, $langId, $data);
            }
        }
    }

    private function resetHeroSliderChildren(int $parentInstanceId): void
    {
        $instances = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('parent_instance_id', $parentInstanceId)
            ->get()
            ->getResultArray();

        if ($instances === []) {
            return;
        }

        $instanceIds = array_map(static fn (array $row): int => (int) $row['id'], $instances);
        $this->db->table('cms_block_instance_translations')->whereIn('instance_id', $instanceIds)->delete();
        $this->db->table('cms_block_instances')->whereIn('id', $instanceIds)->delete();
    }

    private function heroSliderInstanceId(): ?int
    {
        $heroSliderBlockId = $this->blockId('hero_slider');
        if ($heroSliderBlockId === null) {
            return null;
        }

        $homePageId = $this->homePageId();
        if ($homePageId === null) {
            return null;
        }

        $row = $this->db->table('cms_block_instances')
            ->where('block_id', $heroSliderBlockId)
            ->where('owner_type', 'page')
            ->where('owner_id', $homePageId)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function homePageId(): ?int
    {
        $row = $this->db->table('cms_pages')
            ->where('page_type', 'home')
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function blockId(string $key): ?int
    {
        $row = $this->db->table('cms_content_blocks')
            ->where('block_key', $key)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @param string[] $codes
     * @return array<string, int>
     */
    private function langIds(array $codes): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', $codes)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $blockData
     */
    private function upsertTranslation(int $instanceId, int $languageId, array $blockData): void
    {
        $this->upsertRecord('cms_block_instance_translations', [
            'instance_id' => $instanceId,
            'language_id' => $languageId,
        ], [
            'block_data'   => json_encode($blockData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_published' => 1,
        ]);
    }
}
