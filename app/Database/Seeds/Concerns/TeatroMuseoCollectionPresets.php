<?php

declare(strict_types=1);

namespace App\Database\Seeds\Concerns;

/**
 * Single source of truth for the `block_template` + `wizard_config` of the
 * nine TeatroMuseo-specific editorial collections (companias, personas,
 * obras, videos, festivales, exposiciones, cursos, editoriales, prensa,
 * transparencia).
 *
 * Kept separate from `CollectionBlockPresets`, which is the reusable starter
 * kit engine shared across projects (news, portfolio only) — mixing this
 * project's own content into that generic class would couple the CMS engine
 * to a single client's catalog and block every other project consuming the
 * starter kit's block_template repair pass (`WizardConfigSeeder`).
 *
 * Consumed by `CmsTeatroMuseoCollectionSeeder`, alongside
 * `CollectionBlockPresets::news()` for the shared "Noticias" collection.
 */
final class TeatroMuseoCollectionPresets
{
    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function companias(): array
    {
        return self::domainPreset('companias', 'compania_ficha', 'Compañía', 'Company', 'Ficha de compañía artística y sus datos de contacto.');
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function personas(): array
    {
        return self::domainPreset('personas', 'persona_ficha', 'Persona', 'Person', 'Ficha de persona, rol, trayectoria y enlaces.');
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function obras(): array
    {
        return self::domainPreset('obras', 'obra_ficha', 'Obra', 'Work', 'Ficha artística con compañías, personas y recursos relacionados.', [
            self::optionalBlock('rich_text', 'Contenido editorial', 'Sinopsis extendida, historia y notas de la obra.', 2),
            self::optionalBlock('gallery', 'Galería de la obra', 'Imágenes de la obra y sus registros.', 3),
            self::optionalBlock('video_gallery', 'Videos de la obra', 'Registros audiovisuales relacionados.', 4),
        ]);
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function videos(): array
    {
        return self::domainPreset('videos', 'video_ficha', 'Video', 'Video', 'Video embebido con crédito y vínculos editoriales.');
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function festivales(): array
    {
        return self::domainPreset('festivales', 'festival_ficha', 'Festival', 'Festival', 'Festival o edición con programación y recursos.', [
            self::optionalBlock('rich_text', 'Contenido del festival', 'Historia, convocatoria y detalles editoriales.', 2),
            self::optionalBlock('gallery', 'Galería del festival', 'Afiche, registros y material visual.', 3),
            self::optionalBlock('video_gallery', 'Videos del festival', 'Registros audiovisuales relacionados.', 4),
            self::optionalBlock('document_gallery', 'Documentos del festival', 'Bases, programas y documentos descargables.', 5),
        ]);
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function exposiciones(): array
    {
        return self::domainPreset('exposiciones', 'exposicion_ficha', 'Exposición', 'Exhibition', 'Ficha de exposición con fechas, autoría y media.', [
            self::optionalBlock('rich_text', 'Contenido de la exposición', 'Texto curatorial y descripción extendida.', 2),
            self::optionalBlock('gallery', 'Galería de la exposición', 'Obras, montaje y registros de la exposición.', 3),
            self::optionalBlock('document_download', 'Documento de la exposición', 'Catálogo o documento complementario.', 4),
        ]);
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function teatroescuela(): array
    {
        return self::domainPreset('teatroescuela', 'teatroescuela_ficha', 'TeatroEscuela', 'TeatroEscuela', 'Ficha de TeatroEscuela con modalidad, fechas, responsables e inscripción.', [
            self::optionalBlock('rich_text', 'Contenido de TeatroEscuela', 'Descripción extendida, objetivos y requisitos.', 2),
            self::optionalBlock('gallery', 'Galería de TeatroEscuela', 'Imágenes y registros de TeatroEscuela.', 3),
            self::optionalBlock('video_gallery', 'Videos de TeatroEscuela', 'Registros audiovisuales relacionados.', 4),
            self::optionalBlock('document_gallery', 'Documentos de TeatroEscuela', 'Programas, fichas y materiales descargables.', 5),
            self::optionalBlock('external_links', 'Enlaces de TeatroEscuela', 'Recursos externos relacionados.', 6),
        ]);
    }

    /** @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>} */
    public static function editoriales(): array
    {
        return self::domainPreset('editoriales', 'publicacion_metadata', 'Editorial', 'Editorial', 'Publicación editorial con metadatos, contexto y documentos descargables.', [
            self::optionalBlock('rich_text', 'Descripción de la publicación', 'Presentación y contexto editorial.', 2),
            self::optionalBlock('document_gallery', 'Documentos', 'Archivos y versiones descargables.', 3),
            self::optionalBlock('external_links', 'Enlaces relacionados', 'Enlaces externos o plataformas de publicación.', 4),
        ]);
    }

    public static function prensa(): array
    {
        return self::domainPreset('prensa', 'publicacion_metadata', 'Prensa', 'Press', 'Comunicado o material de prensa con documentos descargables.', [
            self::optionalBlock('rich_text', 'Descripción del comunicado', 'Contexto y contenido para medios.', 2),
            self::optionalBlock('document_gallery', 'Documentos de prensa', 'Uno o más archivos asociados al comunicado.', 3),
            self::optionalBlock('external_links', 'Enlaces para medios', 'Enlaces relacionados.', 4),
        ]);
    }

    public static function transparencia(): array
    {
        return self::domainPreset('transparencia', 'publicacion_metadata', 'Transparencia', 'Transparency', 'Documento institucional o de transparencia pública.', [
            self::optionalBlock('rich_text', 'Descripción del documento', 'Contexto institucional del documento.', 2),
            self::optionalBlock('document_gallery', 'Documentos públicos', 'Uno o más archivos asociados al periodo o informe.', 3),
            self::optionalBlock('external_links', 'Enlaces institucionales', 'Enlaces relacionados.', 4),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $optionalBlocks
     * @return array{block_template: array<string, mixed>, wizard_config: array<string, mixed>}
     */
    private static function domainPreset(
        string $type,
        string $blockKey,
        string $esLabel,
        string $enLabel,
        string $helpText,
        array $optionalBlocks = []
    ): array {
        $blocks = [
            [
                'block_key' => $blockKey,
                'label' => $esLabel,
                'help_text' => $helpText,
                'required' => true,
                'locked' => true,
                'auto_create' => true,
                'block_config_defaults' => new \stdClass(),
                'sort_order' => 1,
            ],
            ...$optionalBlocks,
            [
                'block_key' => 'related_entries',
                'label' => 'Relaciones editoriales',
                'help_text' => 'Vínculos opcionales con otras entradas del catálogo.',
                'required' => false,
                'locked' => false,
                'auto_create' => false,
                'block_config_defaults' => new \stdClass(),
                'sort_order' => count($optionalBlocks) + 2,
            ],
        ];

        return [
            'block_template' => [
                'version' => '1.0',
                'blocks' => $blocks,
            ],
            'wizard_config' => [
                'type' => $type,
                'steps' => [
                    [
                        'step_title' => $esLabel,
                        'step_hint' => $helpText,
                        'fields' => [
                            ['key' => 'title', 'label' => $esLabel, 'type' => 'text', 'required' => true],
                            ['key' => 'excerpt', 'label' => 'Resumen', 'type' => 'textarea', 'required' => false],
                        ],
                    ],
                    [
                        'step_title' => 'Publicación',
                        'step_hint' => 'Revisa la estructura y publica cuando la ficha esté lista.',
                        'fields' => [
                            ['key' => 'meta_title', 'label' => 'Título SEO', 'type' => 'text', 'required' => false],
                            ['key' => 'meta_description', 'label' => 'Descripción SEO', 'type' => 'textarea', 'required' => false],
                            ['key' => 'featured_image', 'label' => 'Imagen destacada', 'type' => 'image', 'required' => false],
                        ],
                    ],
                ],
                'labels' => ['es' => $esLabel, 'en' => $enLabel],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function optionalBlock(string $blockKey, string $label, string $helpText, int $sortOrder): array
    {
        return [
            'block_key' => $blockKey,
            'label' => $label,
            'help_text' => $helpText,
            'required' => false,
            'locked' => false,
            'auto_create' => false,
            'block_config_defaults' => new \stdClass(),
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, array{block_template: array<string, mixed>, wizard_config: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [
            'companias' => self::companias(),
            'personas' => self::personas(),
            'obras' => self::obras(),
            'videos' => self::videos(),
            'festivales' => self::festivales(),
            'exposiciones' => self::exposiciones(),
            'teatroescuela' => self::teatroescuela(),
            'editoriales' => self::editoriales(),
            'prensa' => self::prensa(),
            'transparencia' => self::transparencia(),
        ];
    }
}
