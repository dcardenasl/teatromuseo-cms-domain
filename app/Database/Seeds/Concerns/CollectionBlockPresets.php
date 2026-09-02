<?php

declare(strict_types=1);

namespace App\Database\Seeds\Concerns;

/**
 * Single source of truth for the `block_template` + `wizard_config` the real
 * "noticias" collection's `collection_type` ('news') ships with — read by
 * `CmsTeatroMuseoCollectionSeeder` (see `CollectionBlockPresets::all() +
 * TeatroMuseoCollectionPresets::all()` there).
 *
 * The starter kit's demo `NewsCollectionSeeder` and the `portfolio` preset
 * (used only by the now-removed demo `PortfolioCollectionSeeder`/portafolio
 * collection) were deleted 2026-08-02 along with every other seeder that
 * injected placeholder content — this project's public site must only show
 * content that actually exists on the legacy teatromuseo.cl site. `news`
 * stays because it defines the *structure* (block_template/wizard_config)
 * of the real noticias collection, not example content.
 */
final class CollectionBlockPresets
{
    /**
     * @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>}
     */
    public static function news(): array
    {
        return [
            'block_template' => [
                'version' => '1.0',
                'blocks' => [
                    [
                        'block_key' => 'rich_text',
                        'label' => 'Titular',
                        'help_text' => 'Bloque principal de la noticia',
                        'required' => true,
                        'locked' => true,
                        'block_config_defaults' => new \stdClass(),
                        'sort_order' => 1,
                    ],
                    [
                        'block_key' => 'gallery',
                        'label' => 'Galería de la noticia',
                        'help_text' => 'La portada se muestra automáticamente como primera imagen; aquí puedes agregar imágenes adicionales.',
                        'required' => false,
                        'locked' => false,
                        'auto_create' => false,
                        'block_config_defaults' => new \stdClass(),
                        'sort_order' => 2,
                    ],
                ],
            ],
            'wizard_config' => [
                'type' => 'news',
                'steps' => [
                    ['step_title' => 'Titular y resumen', 'step_hint' => 'Título visible para la noticia y una breve bajada informativa', 'fields' => [
                        ['key' => 'title', 'label' => 'Titular', 'type' => 'text', 'required' => true],
                        ['key' => 'excerpt', 'label' => 'Resumen', 'type' => 'textarea', 'required' => false],
                    ]],
                    ['step_title' => 'Imagen destacada', 'step_hint' => 'Portada de la noticia (biblioteca o URL)', 'fields' => [['key' => 'featured_image', 'label' => 'Imagen destacada', 'type' => 'image', 'required' => false]]],
                ],
            ],
        ];
    }

    /**
     * Collection-type key => preset. Project-specific collections live in
     * `TeatroMuseoCollectionPresets` — merged in at the call site (see
     * `CmsTeatroMuseoCollectionSeeder`).
     *
     * @return array<string, array{block_template: array<string, mixed>, wizard_config: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [
            'news' => self::news(),
        ];
    }
}
