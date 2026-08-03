<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

final class TranslationResourceCatalog
{
    /**
     * Fields considered part of the public translation payload for each resource.
     *
     * The audit service uses the `required` flag to decide whether a blank value
     * is an initial missing translation (`incomplete`) or a cross-language
     * inconsistency (`mismatch`) when another locale has content.
     *
     * `compareToSource` marks fields that are genuine free-text editorial
     * content — a non-default-language value that is byte-identical to the
     * default language's value on one of these fields means nobody has
     * actually translated it yet (`evaluateTranslationState()` reports
     * `untranslated`), as opposed to a technical/structural field (slug,
     * file ids, `og_type`, `robots`, `schema_data`, `custom_url`) that can
     * legitimately be identical across languages by design — this site's own
     * pages intentionally reuse the same slug across locales. Omitted =
     * false, i.e. never compared. See TASKS.md PERF-I18N-001 (root-caused
     * 2026-08-02: the legacy WordPress migration seeded every non-Spanish
     * translation row with the Spanish text as a placeholder, and this audit
     * never checked for that, so 98% of migrated entries silently reported
     * "complete").
     *
     * @var array<string, array{table: string, fk: string, fields: array<string, array{required: bool, compareToSource?: bool}>}>
     */
    private const RESOURCE_DEFINITIONS = [
        'setting' => [
            'table' => 'cms_setting_translations',
            'fk' => 'setting_id',
            'fields' => [
                'setting_value' => ['required' => true],
            ],
        ],
        'menu' => [
            'table' => 'cms_menu_translations',
            'fk' => 'menu_id',
            'fields' => [
                'name' => ['required' => true, 'compareToSource' => true],
            ],
        ],
        'menu_item' => [
            'table' => 'cms_menu_item_translations',
            'fk' => 'menu_item_id',
            'fields' => [
                'label' => ['required' => true, 'compareToSource' => true],
                'custom_url' => ['required' => false],
            ],
        ],
        'page' => [
            'table' => 'cms_page_translations',
            'fk' => 'page_id',
            'fields' => [
                'slug' => ['required' => true],
                'title' => ['required' => true, 'compareToSource' => true],
                'excerpt' => ['required' => false, 'compareToSource' => true],
                'meta_title' => ['required' => false, 'compareToSource' => true],
                'meta_description' => ['required' => false, 'compareToSource' => true],
                'og_image_file_id' => ['required' => false],
                'og_image_url' => ['required' => false],
                'og_type' => ['required' => false],
                'canonical_url' => ['required' => false],
                'robots' => ['required' => false],
                'schema_data' => ['required' => false],
            ],
        ],
        'collection' => [
            'table' => 'cms_collection_translations',
            'fk' => 'collection_id',
            'fields' => [
                'slug' => ['required' => true],
                'name' => ['required' => true, 'compareToSource' => true],
                'description' => ['required' => false, 'compareToSource' => true],
                'listing_title' => ['required' => false, 'compareToSource' => true],
                'listing_intro' => ['required' => false, 'compareToSource' => true],
                'default_meta_title' => ['required' => false, 'compareToSource' => true],
                'default_meta_description' => ['required' => false, 'compareToSource' => true],
                'entry_cta_label' => ['required' => false, 'compareToSource' => true],
            ],
        ],
        'category' => [
            'table' => 'cms_category_translations',
            'fk' => 'category_id',
            'fields' => [
                'name' => ['required' => true, 'compareToSource' => true],
                'slug' => ['required' => true],
                'description' => ['required' => false, 'compareToSource' => true],
                'meta_title' => ['required' => false, 'compareToSource' => true],
                'meta_description' => ['required' => false, 'compareToSource' => true],
            ],
        ],
        'tag' => [
            'table' => 'cms_tag_translations',
            'fk' => 'tag_id',
            'fields' => [
                'name' => ['required' => true, 'compareToSource' => true],
                'slug' => ['required' => true],
            ],
        ],
        'entry' => [
            'table' => 'cms_entry_translations',
            'fk' => 'entry_id',
            'fields' => [
                'slug' => ['required' => true],
                'title' => ['required' => true, 'compareToSource' => true],
                'excerpt' => ['required' => false, 'compareToSource' => true],
                'featured_file_id' => ['required' => false],
                'meta_title' => ['required' => false, 'compareToSource' => true],
                'meta_description' => ['required' => false, 'compareToSource' => true],
                'og_image_file_id' => ['required' => false],
                'og_type' => ['required' => false],
                'canonical_url' => ['required' => false],
                'robots' => ['required' => false],
                'schema_data' => ['required' => false],
            ],
        ],
        'form' => [
            'table' => 'cms_form_translations',
            'fk' => 'form_id',
            'fields' => [
                'name' => ['required' => true, 'compareToSource' => true],
                'submit_label' => ['required' => true, 'compareToSource' => true],
            ],
        ],
        'form_field' => [
            'table' => 'cms_form_field_translations',
            'fk' => 'form_field_id',
            'fields' => [
                'label' => ['required' => true, 'compareToSource' => true],
            ],
        ],
        'block_instance' => [
            'table' => 'cms_block_instance_translations',
            'fk' => 'instance_id',
            'fields' => [
                'block_data' => ['required' => false],
            ],
        ],
    ];

    /**
     * Block schema fields considered part of translation content.
     */
    private const AUDITABLE_BLOCK_FIELD_TYPES = [
        'string',
        'text',
        'textarea',
        'richtext',
        'url',
    ];

    /**
     * @return array{table: string, fk: string, fields: array<string, array{required: bool}>}|null
     */
    public static function definition(string $resourceType): ?array
    {
        return self::RESOURCE_DEFINITIONS[$resourceType] ?? null;
    }

    /**
     * @return array<string, array{required: bool}>
     */
    public static function fields(string $resourceType): array
    {
        return self::definition($resourceType)['fields'] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function fieldNames(string $resourceType): array
    {
        return array_keys(self::fields($resourceType));
    }

    public static function table(string $resourceType): ?string
    {
        return self::definition($resourceType)['table'] ?? null;
    }

    public static function foreignKey(string $resourceType): ?string
    {
        return self::definition($resourceType)['fk'] ?? null;
    }

    /**
     * @param array<string, mixed> $fieldDefinition
     */
    public static function isAuditableBlockField(array $fieldDefinition): bool
    {
        if (array_key_exists('translatable', $fieldDefinition) && $fieldDefinition['translatable'] === false) {
            return false;
        }

        $type = strtolower((string) ($fieldDefinition['type'] ?? 'string'));

        return in_array($type, self::AUDITABLE_BLOCK_FIELD_TYPES, true);
    }
}
