<?php

declare(strict_types=1);

return [
    'languages' => [
        'cannot_deactivate_default'         => 'No se puede desactivar el idioma predeterminado.',
        'must_have_default'                 => 'Debe haber al menos un idioma predeterminado. Marca otro idioma como predeterminado en su lugar.',
        'cannot_delete_default'             => 'No se puede eliminar el idioma predeterminado.',
        'code_must_be_unique'               => 'El código del idioma debe ser único.',
        'code_already_taken'                => "El código del idioma '{0}' ya está en uso.",
    ],
    'block_types' => [
        'block_key_must_be_unique'          => 'La clave del bloque debe ser única.',
        'block_key_already_taken'           => "La clave del bloque '{0}' ya está en uso.",
        'in_use'                             => 'No se puede eliminar: este tipo de bloque está en uso en {0} instancia(s) — {1}. Elimina o reemplaza esos bloques primero.',
        'usage_page'                         => 'Página',
        'usage_entry'                        => 'Entrada',
        'usage_instance'                     => 'instancia',
    ],
    'settings' => [
        'create_success' => 'Configuración creada exitosamente.',
        'update_success' => 'Configuración actualizada exitosamente.',
        'delete_success' => 'Configuración eliminada exitosamente.',
    ],
    'file_references' => [
        'missing_table' => 'Falta la tabla requerida `cms_file_references`. Ejecuta las migraciones del dominio.',
        'unsupported_resource' => 'Tipo de recurso no soportado para referencias de archivos CMS: {0}.',
        'sync_failed' => 'No se pudieron sincronizar las referencias de archivos CMS para {0} #{1}.',
    ],
    'entry_facets' => [
        'sync_failed' => 'No se pudieron sincronizar los valores de faceta/orden de la entrada #{0}.',
    ],
    'entry_references' => [
        'required' => 'El campo de referencia de entrada {0} es obligatorio.',
        'min_items' => 'La lista de referencias {0} no contiene suficientes entradas.',
        'max_items' => 'La lista de referencias {0} contiene demasiadas entradas.',
        'collection' => 'El campo de referencia {0} apunta a una colección inválida.',
        'self' => 'El campo de referencia {0} no puede apuntar a su propia entrada.',
        'not_found' => 'Una o más entradas referenciadas por {0} no existen en la colección esperada.',
        'shape' => 'El campo de referencia {0} tiene un valor inválido.',
        'sync_failed' => 'No se pudieron sincronizar las relaciones de la entrada #{0}.',
    ],
];
