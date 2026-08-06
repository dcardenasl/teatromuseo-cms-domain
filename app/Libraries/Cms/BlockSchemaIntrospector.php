<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use dcardenasl\Ci4ApiCore\Support\JsonCastNormalizer;

final class BlockSchemaIntrospector
{
    public function __construct(private readonly FieldPrimitiveRegistry $registry = new FieldPrimitiveRegistry())
    {
    }

    /**
     * Accepts anything JsonCastNormalizer::toArray() accepts — not just a
     * plain array — so a caller handing over a raw `json`-cast Entity
     * property (stdClass, possibly nested) can never silently produce an
     * empty result here. This is the single choke point for reading a block
     * type's schema_definition; normalizing here means callers don't each
     * need to remember to pre-normalize.
     *
     * @return array{
     *     contains_richtext: bool,
     *     contains_image: bool,
     *     contains_file: bool,
     *     required_fields: list<string>,
     *     translatable_fields: list<string>,
     *     unsupported_fields: list<string>,
     *     reference_fields: array<string, array{type: string, collection_keys: list<string>, min_items: int|null, max_items: int|null}>,
     *     fields: array<string, array<string, mixed>>
     * }
     */
    public function introspect(mixed $schema): array
    {
        $schema = JsonCastNormalizer::toArray($schema);
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $normalizedFields = [];
        $required = [];
        $translatable = [];
        $unsupported = [];
        $referenceFields = [];

        foreach ($fields as $fieldKey => $definition) {
            if (! is_string($fieldKey)) {
                continue;
            }

            $fieldDefinition = is_array($definition) ? $definition : [];
            $primitive = $this->registry->normalize((string) ($fieldDefinition['type'] ?? 'string'));

            $normalizedFields[$fieldKey] = $fieldDefinition + [
                'type' => (string) ($fieldDefinition['type'] ?? 'string'),
                'primitive' => $primitive,
            ];

            if ((bool) ($fieldDefinition['required'] ?? false)) {
                $required[] = $fieldKey;
            }

            if ($this->registry->isTranslatable($primitive)) {
                $translatable[] = $fieldKey;
            }

            if (! $this->registry->isSupported($primitive)) {
                $unsupported[] = $fieldKey;
            }

            if ($this->registry->isEntryReference($primitive)) {
                $collectionKeys = $fieldDefinition['collection_keys'] ?? $fieldDefinition['allowed_collections'] ?? [];
                if (isset($fieldDefinition['collection_key']) && is_string($fieldDefinition['collection_key'])) {
                    $collectionKeys = [$fieldDefinition['collection_key']];
                }
                $referenceFields[$fieldKey] = [
                    'type' => $primitive,
                    'collection_keys' => is_array($collectionKeys)
                        ? array_values(array_filter(array_map(static fn (mixed $key): string => trim((string) $key), $collectionKeys)))
                        : [],
                    'min_items' => isset($fieldDefinition['min_items']) ? max(0, (int) $fieldDefinition['min_items']) : null,
                    'max_items' => isset($fieldDefinition['max_items']) ? max(0, (int) $fieldDefinition['max_items']) : null,
                ];
            }
        }

        $primitives = array_column($normalizedFields, 'primitive');
        $containsMediaReference = in_array('media_reference', $primitives, true);

        return [
            'contains_richtext' => in_array('richtext', $primitives, true),
            'contains_image' => $containsMediaReference,
            'contains_file' => $containsMediaReference,
            'required_fields' => $required,
            'translatable_fields' => $translatable,
            'unsupported_fields' => $unsupported,
            'reference_fields' => $referenceFields,
            'fields' => $normalizedFields,
        ];
    }
}
