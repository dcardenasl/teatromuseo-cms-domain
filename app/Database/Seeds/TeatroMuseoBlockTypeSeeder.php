<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Domain-specific block catalog for TeatroMuseo.
 *
 * The generic starter catalog remains in CmsBlockTypeSeeder. Keeping these
 * definitions separate makes the domain layer reusable while allowing the
 * museum to evolve its editorial vocabulary independently.
 */
final class TeatroMuseoBlockTypeSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        foreach ($this->blocks() as $block) {
            $this->upsertRecord('cms_content_blocks', ['block_key' => $block['block_key']], $block);
        }
    }

    /** @return list<array<string, mixed>> */
    private function blocks(): array
    {
        return [
            $this->block('compania_ficha', 'Ficha de compañía', 'Company profile', 'Ficha estructurada de una compañía artística.', [
                'name' => ['type' => 'string', 'label' => 'Nombre visible', 'required' => false],
                'summary' => ['type' => 'textarea', 'label' => 'Resumen', 'required' => false],
                'description' => ['type' => 'richtext', 'label' => 'Descripción', 'required' => false],
                'website' => ['type' => 'url', 'label' => 'Sitio web', 'required' => false],
            ], [
                'logo' => ['type' => 'media_reference', 'label' => 'Logo', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('persona_ficha', 'Ficha de persona', 'Person profile', 'Ficha de una persona vinculada al catálogo editorial.', [
                'name' => ['type' => 'string', 'label' => 'Nombre visible', 'required' => false],
                'role' => ['type' => 'string', 'label' => 'Rol o disciplina', 'required' => false],
                'bio' => ['type' => 'richtext', 'label' => 'Biografía', 'required' => false],
                'website' => ['type' => 'url', 'label' => 'Sitio web', 'required' => false],
            ], [
                'portrait' => ['type' => 'media_reference', 'label' => 'Retrato', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('obra_ficha', 'Ficha de obra', 'Work profile', 'Ficha artística con autoría, compañía y recursos.', [
                'subtitle' => ['type' => 'string', 'label' => 'Subtítulo', 'required' => false],
                'synopsis' => ['type' => 'richtext', 'label' => 'Sinopsis', 'required' => false],
                'duration' => ['type' => 'string', 'label' => 'Duración', 'required' => false],
                'premiere_date' => ['type' => 'date', 'label' => 'Fecha de estreno', 'required' => false],
                'performance_date' => ['type' => 'date', 'label' => 'Fecha de función', 'required' => false],
                'performance_time' => ['type' => 'string', 'label' => 'Hora de función', 'required' => false],
                'venue' => ['type' => 'string', 'label' => 'Lugar de función', 'required' => false],
                'price_regular' => ['type' => 'string', 'label' => 'Precio general', 'required' => false],
                'price_discount' => ['type' => 'string', 'label' => 'Precio rebajado', 'required' => false],
                'audience' => ['type' => 'string', 'label' => 'Público', 'required' => false],
                'company' => ['type' => 'entry_reference', 'collection_key' => 'companias', 'label' => 'Compañía', 'required' => false],
                'people' => ['type' => 'entry_reference_list', 'collection_key' => 'personas', 'label' => 'Personas participantes', 'required' => false, 'max_items' => 30],
            ], [
                'featured_media' => ['type' => 'media_reference', 'label' => 'Imagen principal', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('catalog_item_header', 'Cabecera de ficha de catálogo', 'Catalog item header', 'Cabecera dinámica para la ficha pública del catálogo.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [
                'fallback_image_url' => ['type' => 'string', 'label' => 'Imagen de respaldo (URL)', 'required' => false, 'default' => ''],
            ], true, false),
            $this->block('catalog_item_details', 'Detalles de ficha de catálogo', 'Catalog item details', 'Ficha técnica dinámica (técnica, dimensiones, etc) para la ficha pública del catálogo.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [], true, false),
            $this->block('catalog_item_content', 'Contenido de ficha de catálogo', 'Catalog item content', 'Descripción extendida para la ficha pública del catálogo.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [], true, false),
            $this->block('catalog_item_gallery', 'Galería de ficha de catálogo', 'Catalog item gallery', 'Galería dinámica para la ficha pública del catálogo.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [
                'fallback_gallery_images' => ['type' => 'string', 'label' => 'Imágenes de respaldo (una URL por línea)', 'required' => false, 'default' => ''],
            ], true, false),
            $this->block('event_item_header', 'Cabecera de evento', 'Event item header', 'Cabecera dinámica para la ficha pública de programación.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [
                'fallback_image_url' => ['type' => 'string', 'label' => 'Imagen de respaldo (URL)', 'required' => false, 'default' => ''],
            ], true, false),
            $this->block('event_item_details', 'Detalles de evento', 'Event item details', 'Ficha técnica dinámica (fechas, lugar, precio, tickets) para la ficha pública de programación.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [], true, false),
            $this->block('event_item_content', 'Contenido de evento', 'Event item content', 'Descripción extendida y sinopsis para la ficha pública de programación.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [], true, false),
            $this->block('event_item_gallery', 'Galería de evento', 'Event item gallery', 'Galería dinámica para la ficha pública de programación.', [
                'fallback_title' => ['type' => 'string', 'label' => 'Título de respaldo', 'required' => false],
            ], [
                'fallback_gallery_images' => ['type' => 'string', 'label' => 'Imágenes de respaldo (una URL por línea)', 'required' => false, 'default' => ''],
            ], true, false),
            $this->block('video_ficha', 'Ficha de video', 'Video profile', 'Video de YouTube u otro proveedor con metadatos editoriales.', [
                'provider' => ['type' => 'select', 'label' => 'Proveedor', 'options' => ['youtube', 'vimeo', 'other'], 'required' => true],
                'video_id' => ['type' => 'string', 'label' => 'ID del video', 'required' => false],
                'video_url' => ['type' => 'url', 'label' => 'URL del video', 'required' => false],
                'recorded_at' => ['type' => 'date', 'label' => 'Fecha del registro', 'required' => false],
                'duration' => ['type' => 'string', 'label' => 'Duración', 'required' => false],
                'credit' => ['type' => 'string', 'label' => 'Crédito', 'required' => false],
                'related_works' => ['type' => 'entry_reference_list', 'collection_key' => 'obras', 'label' => 'Obras relacionadas', 'required' => false, 'max_items' => 20],
            ], [
                'thumbnail' => ['type' => 'media_reference', 'label' => 'Miniatura', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('festival_ficha', 'Ficha de festival', 'Festival profile', 'Festival o edición con fechas, estado y programación.', [
                'edition' => ['type' => 'string', 'label' => 'Edición', 'required' => false],
                'start_date' => ['type' => 'date', 'label' => 'Inicio', 'required' => false],
                'end_date' => ['type' => 'date', 'label' => 'Término', 'required' => false],
                'venue' => ['type' => 'string', 'label' => 'Lugar', 'required' => false],
                'status' => ['type' => 'select', 'label' => 'Estado', 'options' => ['upcoming', 'open', 'finished', 'cancelled'], 'required' => false],
                'works' => ['type' => 'entry_reference_list', 'collection_key' => 'obras', 'label' => 'Obras programadas', 'required' => false, 'max_items' => 100],
                'videos' => ['type' => 'entry_reference_list', 'collection_key' => 'videos', 'label' => 'Videos relacionados', 'required' => false, 'max_items' => 50],
            ], [
                'cover' => ['type' => 'media_reference', 'label' => 'Imagen de portada', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('exposicion_ficha', 'Ficha de exposición', 'Exhibition profile', 'Exposición con fechas, autoría, curaduría y media.', [
                'author' => ['type' => 'entry_reference_list', 'collection_key' => 'personas', 'label' => 'Autoría', 'required' => false, 'max_items' => 30],
                'curator' => ['type' => 'entry_reference_list', 'collection_key' => 'personas', 'label' => 'Curaduría', 'required' => false, 'max_items' => 10],
                'opening_date' => ['type' => 'date', 'label' => 'Inauguración', 'required' => false],
                'closing_date' => ['type' => 'date', 'label' => 'Cierre', 'required' => false],
                'venue' => ['type' => 'string', 'label' => 'Lugar', 'required' => false],
                'description' => ['type' => 'richtext', 'label' => 'Descripción', 'required' => false],
            ], [
                'cover' => ['type' => 'media_reference', 'label' => 'Imagen principal', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('curso_ficha', 'Ficha de curso', 'Course profile', 'Curso con modalidad, fechas, responsables e inscripción.', [
                'category' => ['type' => 'string', 'label' => 'Categoría', 'required' => false],
                'modality' => ['type' => 'select', 'label' => 'Modalidad', 'options' => ['presencial', 'online', 'hibrido'], 'required' => false],
                'start_date' => ['type' => 'date', 'label' => 'Inicio', 'required' => false],
                'end_date' => ['type' => 'date', 'label' => 'Término', 'required' => false],
                'schedule' => ['type' => 'string', 'label' => 'Horario', 'required' => false],
                'days' => ['type' => 'string', 'label' => 'Días', 'required' => false],
                'duration' => ['type' => 'string', 'label' => 'Duración', 'required' => false],
                'venue' => ['type' => 'string', 'label' => 'Lugar', 'required' => false],
                'capacity' => ['type' => 'number', 'label' => 'Cupos', 'required' => false],
                'price' => ['type' => 'number', 'label' => 'Precio', 'required' => false],
                'enrollment_fee' => ['type' => 'number', 'label' => 'Matrícula', 'required' => false],
                'requirements' => ['type' => 'richtext', 'label' => 'Requisitos', 'required' => false],
                'objectives' => ['type' => 'richtext', 'label' => 'Objetivos', 'required' => false],
                'history' => ['type' => 'richtext', 'label' => 'Antecedentes', 'required' => false],
                'instructors' => ['type' => 'entry_reference_list', 'collection_key' => 'personas', 'label' => 'Docentes', 'required' => false, 'max_items' => 20],
                'registration_url' => ['type' => 'url', 'label' => 'URL de inscripción', 'required' => false],
                'contact_email' => ['type' => 'text', 'label' => 'Correo de contacto', 'required' => false],
                'video_url' => ['type' => 'url', 'label' => 'Video relacionado', 'required' => false],
            ], [
                'cover' => ['type' => 'media_reference', 'label' => 'Imagen de portada', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('publicacion_metadata', 'Metadatos de publicación', 'Publication metadata', 'Metadatos comunes para editoriales, prensa y documentos.', [
                'publication_type' => ['type' => 'select', 'label' => 'Tipo', 'options' => ['editorial', 'press', 'transparency', 'other'], 'required' => true],
                'authors' => ['type' => 'entry_reference_list', 'collection_key' => 'personas', 'label' => 'Autores', 'required' => false, 'max_items' => 30],
                'publication_date' => ['type' => 'date', 'label' => 'Fecha de publicación', 'required' => false],
                'publisher' => ['type' => 'string', 'label' => 'Editorial o institución', 'required' => false],
                'document_link' => ['type' => 'url', 'label' => 'Enlace del documento', 'required' => false],
            ], [
                'cover' => ['type' => 'media_reference', 'label' => 'Portada', 'required' => false, 'accept' => 'image'],
            ], false),
            $this->block('related_entries', 'Entradas relacionadas', 'Related entries', 'Lista ordenada de relaciones editoriales con presentación configurable.', [
                'relation_type' => ['type' => 'select', 'label' => 'Tipo de relación', 'options' => ['related', 'recommended', 'prerequisite', 'sequel'], 'required' => true],
                'entries' => [
                    'type' => 'entry_reference_list',
                    'collection_keys' => ['companias', 'personas', 'obras', 'videos', 'festivales', 'exposiciones', 'cursos', 'publicaciones', 'noticias'],
                    'label' => 'Entradas',
                    'required' => false,
                    'max_items' => 12,
                ],
            ], [], true, true),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, array<string, mixed>> $configFields
     * @return array<string, mixed>
     */
    private function block(
        string $key,
        string $name,
        string $englishName,
        string $description,
        array $fields,
        array $configFields,
        bool $supportsPages,
        bool $supportsEntries = true
    ): array {
        return [
            'block_key' => $key,
            'name' => $name,
            'description' => $description . ' / ' . $englishName,
            'category' => 'teatro_museo',
            'icon' => 'layers',
            'schema_definition' => json_encode([
                'fields' => $fields,
                'config_fields' => $configFields,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supports_pages' => $supportsPages ? 1 : 0,
            'supports_entries' => $supportsEntries ? 1 : 0,
            'is_container' => $key === 'related_entries' ? 0 : 0,
            'is_active' => 1,
            'sort_order' => 400 + count($fields),
        ];
    }
}
