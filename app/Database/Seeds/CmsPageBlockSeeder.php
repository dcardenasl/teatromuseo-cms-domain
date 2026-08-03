<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Seeds block instances for the homepage and contact page.
 * Idempotent: upserts block instances by owner + block type + sort order.
 */
class CmsPageBlockSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $blockIds = $this->blockIds([
            'hero_slider',
            'collection_grid',
            'cta',
            'page_header',
            'form_embed',
            'contact_info',
            'map_embed',
            'social_links',
        ]);

        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
        $homePageId = $this->pageIdByType('home');
        $contactPageId = $this->pageIdByType('contact');

        if ($homePageId === null || $contactPageId === null || ! isset($langIds['es'], $langIds['en'], $langIds['fr'], $langIds['pt'])) {
            echo "CmsPageBlockSeeder: missing prerequisite pages, blocks or languages.\n";
            return;
        }

        $this->resetPageBlocks($homePageId);
        $this->resetPageBlocks($contactPageId);

        $homeBlocks = [
            [
                'block_key' => 'hero_slider',
                'sort_order' => 1,
                'config'    => [
                    'autoplay'          => true,
                    'interval'          => 5000,
                    'transition'        => 'fade',
                    'overlay_opacity'   => '20',
                    'caption_position'  => 'below',
                    'controls_position' => 'below',
                    'css_class'         => '',
                ],
                // hero_slider has no translatable `fields` of its own (schema_definition
                // in CmsBlockTypeSeeder declares `fields => []`) — the actual slide content
                // lives on its `slide_banner` children, seeded separately by
                // CmsHeroSliderChildrenSeeder. No `data` key here (no translation rows to write).
            ],
            [
                'block_key' => 'collection_grid',
                'sort_order' => 2,
                'config'    => [
                    'collection_key'  => 'noticias',
                    'items_limit'     => 3,
                    'order_by'        => 'published_at',
                    'order_direction' => 'desc',
                    'layout_variant'  => 'cards',
                    'image_aspect_ratio' => '1/1',
                    'css_class'       => '',
                ],
                'data'      => [
                    'es' => [
                        'section_title'    => 'Noticias',
                        'section_subtitle' => 'Mantente al día con las últimas publicaciones.',
                        'view_all_label'   => 'Ver todas las noticias',
                        'view_all_url'     => '/noticias',
                        'empty_message'    => 'Aún no hay noticias publicadas.',
                    ],
                    'en' => [
                        'section_title'    => 'News',
                        'section_subtitle' => 'Stay up to date with the latest posts.',
                        'view_all_label'   => 'View all news',
                        'view_all_url'     => '/news',
                        'empty_message'    => 'No news posts are available yet.',
                    ],
                    'fr' => [
                        'section_title'    => 'Actualités',
                        'section_subtitle' => 'Restez informé des dernières publications.',
                        'view_all_label'   => 'Voir toutes les actualités',
                        'view_all_url'     => '/actualites',
                        'empty_message'    => 'Aucune actualité publiée pour le moment.',
                    ],
                    'pt' => [
                        'section_title'    => 'Notícias',
                        'section_subtitle' => 'Acompanhe as publicações mais recentes.',
                        'view_all_label'   => 'Ver todas as notícias',
                        'view_all_url'     => '/noticias',
                        'empty_message'    => 'Ainda não há notícias publicadas.',
                    ],
                ],
            ],
            [
                'block_key' => 'cta',
                'sort_order' => 3,
                'config'    => [
                    'variant'   => 'blue',
                    'css_class' => '',
                ],
                'data'      => [
                    'es' => [
                        'heading' => '¿Quieres hablar con nosotros?',
                        'text'    => 'Usa el formulario de contacto para escribirnos. Te responderemos a la brevedad.',
                        'label'   => 'Ir a contacto',
                        'url'     => '/contacto',
                    ],
                    'en' => [
                        'heading' => 'Want to talk to us?',
                        'text'    => 'Use the contact form to reach out. We will reply as soon as possible.',
                        'label'   => 'Go to contact',
                        'url'     => '/contact',
                    ],
                    'fr' => [
                        'heading' => 'Vous souhaitez nous parler ?',
                        'text'    => 'Utilisez le formulaire de contact pour nous écrire. Nous vous répondrons dès que possible.',
                        'label'   => 'Aller au contact',
                        'url'     => '/contact',
                    ],
                    'pt' => [
                        'heading' => 'Quer falar conosco?',
                        'text'    => 'Use o formulário de contato para nos escrever. Responderemos o mais breve possível.',
                        'label'   => 'Ir para contato',
                        'url'     => '/contato',
                    ],
                ],
            ],
        ];

        $contactBlocks = [
            [
                'block_key' => 'page_header',
                'sort_order' => 1,
                'config'    => [
                    'bg_color'  => 'bg-gray-100',
                    'css_class' => '',
                ],
                'data'      => [
                    'es' => [
                        'heading'          => 'Contacto',
                        'subheading'       => 'TeatroMuseo quiere saber de ti.',
                        'breadcrumb_label' => 'Inicio',
                        'breadcrumb_url'   => '/',
                    ],
                    'en' => [
                        'heading'          => 'Contact',
                        'subheading'       => 'TeatroMuseo would love to hear from you.',
                        'breadcrumb_label' => 'Home',
                        'breadcrumb_url'   => '/',
                    ],
                    'fr' => [
                        'heading'          => 'Contact',
                        'subheading'       => 'TeatroMuseo serait ravi de vous lire.',
                        'breadcrumb_label' => 'Accueil',
                        'breadcrumb_url'   => '/',
                    ],
                    'pt' => [
                        'heading'          => 'Contato',
                        'subheading'       => 'O TeatroMuseo adoraria ouvir você.',
                        'breadcrumb_label' => 'Início',
                        'breadcrumb_url'   => '/',
                    ],
                ],
            ],
            [
                'block_key' => 'form_embed',
                'sort_order' => 2,
                'config'    => [
                    'form_key'  => 'contact',
                    'css_class' => '',
                ],
                'data'      => [
                    'es' => [],
                    'en' => [],
                    'fr' => [],
                    'pt' => [],
                ],
            ],
            [
                'block_key' => 'contact_info',
                'sort_order' => 3,
                'config'    => [
                    'layout'    => 'stacked',
                    'css_class' => '',
                ],
                'data'      => [
                    'es' => [
                        'section_title' => 'Dónde está TeatroMuseo',
                        'address_label'  => 'Dirección',
                        'address'        => 'Avenida Providencia 1234, Santiago, Chile',
                        'phone_label'    => 'Teléfono',
                        'phone'          => '+56 2 2345 6789',
                        'email_label'    => 'Correo',
                        'email'          => 'contacto@teatromuseo.local',
                        'hours_label'    => 'Horario',
                        'hours'          => "Lunes a viernes: 09:00 - 18:00\nSábado: 10:00 - 14:00",
                    ],
                    'en' => [
                        'section_title' => 'Where TeatroMuseo is located',
                        'address_label'  => 'Address',
                        'address'        => 'Avenida Providencia 1234, Santiago, Chile',
                        'phone_label'    => 'Phone',
                        'phone'          => '+56 2 2345 6789',
                        'email_label'    => 'Email',
                        'email'          => 'contact@teatromuseo.local',
                        'hours_label'    => 'Hours',
                        'hours'          => "Monday to Friday: 09:00 - 18:00\nSaturday: 10:00 - 14:00",
                    ],
                    'fr' => [
                        'section_title' => 'Où se trouve TeatroMuseo',
                        'address_label'  => 'Adresse',
                        'address'        => 'Avenida Providencia 1234, Santiago, Chili',
                        'phone_label'    => 'Téléphone',
                        'phone'          => '+56 2 2345 6789',
                        'email_label'    => 'Courriel',
                        'email'          => 'contact@teatromuseo.local',
                        'hours_label'    => 'Horaires',
                        'hours'          => "Lundi au vendredi : 09:00 - 18:00\nSamedi : 10:00 - 14:00",
                    ],
                    'pt' => [
                        'section_title' => 'Onde fica o TeatroMuseo',
                        'address_label'  => 'Endereço',
                        'address'        => 'Avenida Providencia 1234, Santiago, Chile',
                        'phone_label'    => 'Telefone',
                        'phone'          => '+56 2 2345 6789',
                        'email_label'    => 'Email',
                        'email'          => 'contacto@teatromuseo.local',
                        'hours_label'    => 'Horário',
                        'hours'          => "Segunda a sexta: 09:00 - 18:00\nSábado: 10:00 - 14:00",
                    ],
                ],
            ],
            [
                'block_key' => 'map_embed',
                'sort_order' => 4,
                'config'    => [
                    'embed_url'    => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3329.0664974635676!2d-70.6508083!3d-33.4474867!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9662c50f83733e8b%3A0xc38fa632e825a1e7!2sPalacio%20de%20La%20Moneda!5e0!3m2!1ses!2scl!4v1700000000000!5m2!1ses!2scl',
                    'aspect_ratio' => '16/9',
                    'height'       => 360,
                    'css_class'    => '',
                ],
                'data'      => [
                    'es' => [
                        'title'   => 'Mapa de ubicación',
                        'caption' => 'Encuéntranos en la sede principal de TeatroMuseo.',
                    ],
                    'en' => [
                        'title'   => 'Location map',
                        'caption' => 'Find us at TeatroMuseo’s main location.',
                    ],
                    'fr' => [
                        'title'   => 'Carte d’emplacement',
                        'caption' => 'Retrouvez-nous au siège principal de TeatroMuseo.',
                    ],
                    'pt' => [
                        'title'   => 'Mapa de localização',
                        'caption' => 'Encontre-nos na sede principal do TeatroMuseo.',
                    ],
                ],
            ],
            [
                'block_key' => 'social_links',
                'sort_order' => 5,
                'config'    => [
                    'css_class' => '',
                ],
                'data'      => [
                    'es' => [
                        'heading' => 'Síguenos en TeatroMuseo',
                    ],
                    'en' => [
                        'heading' => 'Follow TeatroMuseo',
                    ],
                    'fr' => [
                        'heading' => 'Suivez TeatroMuseo',
                    ],
                    'pt' => [
                        'heading' => 'Siga o TeatroMuseo',
                    ],
                ],
            ],
        ];

        $this->seedBlocks($homePageId, 'page', $homeBlocks, $blockIds, $langIds);
        $this->seedBlocks($contactPageId, 'page', $contactBlocks, $blockIds, $langIds);
    }

    /**
     * @param string[] $keys
     * @return array<string, int>
     */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')
            ->whereIn('block_key', $keys)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['block_key']] = (int) $row['id'];
        }

        return $map;
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

    private function pageIdByType(string $pageType): ?int
    {
        $row = $this->db->table('cms_pages')
            ->where('page_type', $pageType)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, int>               $blockIds
     * @param array<string, int>               $langIds
     */
    private function seedBlocks(int $ownerId, string $ownerType, array $blocks, array $blockIds, array $langIds): void
    {
        foreach ($blocks as $block) {
            $blockKey = (string) $block['block_key'];
            $blockId = $blockIds[$blockKey] ?? null;
            if ($blockId === null) {
                continue;
            }

            $instanceId = $this->upsertRecord('cms_block_instances', [
                'block_id'           => $blockId,
                'owner_type'       => $ownerType,
                'owner_id'         => $ownerId,
                'parent_instance_id' => null,
                'sort_order'       => (int) $block['sort_order'],
            ], [
                'column_index'     => null,
                'is_active'        => 1,
                'block_config'     => json_encode($block['config'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($instanceId === null) {
                continue;
            }

            foreach (($block['data'] ?? []) as $langCode => $data) {
                $langId = $langIds[$langCode] ?? null;
                if ($langId === null || ! is_array($data)) {
                    continue;
                }

                $this->upsertBlockTranslation($instanceId, $langId, $data);
            }
        }
    }

    /**
     * @param array<string, mixed> $blockData
     */
    private function upsertBlockTranslation(int $instanceId, int $languageId, array $blockData): void
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
