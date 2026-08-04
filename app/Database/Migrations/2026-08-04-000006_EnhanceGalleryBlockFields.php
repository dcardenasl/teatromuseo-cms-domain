<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Keeps the gallery block editor aligned with its public rendering contract. */
final class EnhanceGalleryBlockFields extends Migration
{
    public function up(): void
    {
        $schema = [
            'fields' => [
                'title' => ['type' => 'string', 'label' => 'Título', 'required' => false],
                'description' => ['type' => 'textarea', 'label' => 'Descripción', 'required' => false],
            ],
            'config_fields' => [
                'presentation_mode' => ['type' => 'select', 'label' => 'Modo de Presentación', 'options' => ['grid', 'inline_preview', 'modal_preview'], 'default' => 'modal_preview', 'required' => false],
                'columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2', '3', '4', '6'], 'default' => '3', 'required' => false],
                'gap' => ['type' => 'select', 'label' => 'Espaciado', 'options' => ['small', 'medium', 'large', 'none'], 'default' => 'medium', 'required' => false],
                'css_class' => ['type' => 'string', 'label' => 'Clase CSS', 'required' => false, 'default' => ''],
            ],
            'allowed_children' => ['gallery_item'],
        ];
        $this->db->table('cms_content_blocks')
            ->where('block_key', 'gallery')
            ->update(['schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function down(): void
    {
        // Keep the richer schema on rollback; it is backwards compatible.
    }
}
