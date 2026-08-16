<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Interfaces\Cms\PageQualityServiceInterface;
use CodeIgniter\Entity\Entity;

final class PageQualityService implements PageQualityServiceInterface
{
    private const VERSION = 'page-quality.v1';
    private const META_TITLE_MAX = 60;
    private const META_DESCRIPTION_MAX = 160;

    public function __construct(
        private readonly \App\Models\PageModel $pageModel,
        private readonly \App\Models\PageTranslationModel $translationModel,
        private readonly \App\Models\LanguageModel $languageModel,
        private readonly \App\Models\BlockInstanceModel $blockInstanceModel,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(int $pageId): array
    {
        $page = $this->pageModel->find($pageId);
        if (! $page instanceof Entity) {
            return [
                'version' => self::VERSION,
                'status' => 'blocked',
                'score' => 0,
                'summary' => ['errors' => 1, 'warnings' => 0, 'passed' => 0],
                'rules' => $this->rules(),
                'checks' => [[
                    'key' => 'page_exists',
                    'status' => 'fail',
                    'severity' => 'error',
                    'message' => 'La página no existe o fue eliminada.',
                ]],
            ];
        }

        $languages = $this->objectRows($this->languageModel
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll());
        $translations = $this->objectRows($this->translationModel
            ->where('page_id', $pageId)
            ->findAll());
        $translationByLanguage = [];
        foreach ($translations as $translation) {
            $translationByLanguage[(int) ($translation->language_id ?? 0)] = $translation;
        }

        $defaultLanguage = null;
        foreach ($languages as $language) {
            if ((bool) ($language->is_default ?? false)) {
                $defaultLanguage = $language;
                break;
            }
        }
        $defaultLanguage ??= $languages[0] ?? null;

        $blocks = $this->blockInstanceModel->findAllWithBlockType('page', $pageId, true);
        $checks = [];

        $this->checkDefaultTranslation($checks, $defaultLanguage, $translationByLanguage);
        $this->checkTranslations($checks, $languages, $translationByLanguage, (int) ($defaultLanguage->id ?? 0));
        $this->checkBlocks($checks, $blocks);
        $this->checkSitemapAndRobots($checks, $page, $defaultLanguage, $translationByLanguage);

        $errors = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'fail' && ($check['severity'] ?? '') === 'error'));
        $warnings = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'warning'));
        $passed = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'pass'));
        $total = $errors + $warnings + $passed;

        return [
            'version' => self::VERSION,
            'status' => $errors > 0 ? 'blocked' : ($warnings > 0 ? 'warning' : 'ready'),
            'score' => $total > 0 ? (int) round(($passed / $total) * 100) : 0,
            'summary' => ['errors' => $errors, 'warnings' => $warnings, 'passed' => $passed],
            'rules' => $this->rules(),
            'checks' => array_values($checks),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param object|null $defaultLanguage
     * @param array<int, object> $translationByLanguage
     */
    private function checkDefaultTranslation(array &$checks, ?object $defaultLanguage, array $translationByLanguage): void
    {
        $languageId = (int) ($defaultLanguage->id ?? 0);
        $translation = $translationByLanguage[$languageId] ?? null;
        $languageCode = strtoupper((string) ($defaultLanguage->code ?? ''));

        $this->addPresenceCheck(
            $checks,
            'default_title',
            'title',
            trim((string) ($translation->title ?? '')) !== '',
            'El título principal es obligatorio.',
            $languageId,
            $languageCode,
        );
        $this->addPresenceCheck(
            $checks,
            'default_slug',
            'slug',
            trim((string) ($translation->slug ?? '')) !== '',
            'El slug de la traducción principal es obligatorio.',
            $languageId,
            $languageCode,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param list<object> $languages
     * @param array<int, object> $translationByLanguage
     */
    private function checkTranslations(array &$checks, array $languages, array $translationByLanguage, int $defaultLanguageId): void
    {
        foreach ($languages as $language) {
            $languageId = (int) ($language->id ?? 0);
            $languageCode = strtoupper((string) ($language->code ?? ''));
            $translation = $translationByLanguage[$languageId] ?? null;
            $prefix = $languageCode !== '' ? " ({$languageCode})" : '';

            if ($languageId !== $defaultLanguageId) {
                $this->addPresenceCheck(
                    $checks,
                    "translation_title_{$languageId}",
                    'title',
                    trim((string) ($translation->title ?? '')) !== '',
                    "Falta el título{$prefix}.",
                    $languageId,
                    $languageCode,
                    'warning',
                );
                $this->addPresenceCheck(
                    $checks,
                    "translation_slug_{$languageId}",
                    'slug',
                    trim((string) ($translation->slug ?? '')) !== '',
                    "Falta el slug{$prefix}.",
                    $languageId,
                    $languageCode,
                    'warning',
                );
            }

            $this->addPresenceCheck(
                $checks,
                "meta_title_presence_{$languageId}",
                'meta_title',
                trim((string) ($translation->meta_title ?? '')) !== '',
                "Falta el meta title{$prefix}; el CMS usará el título como fallback.",
                $languageId,
                $languageCode,
                'warning',
            );
            $this->addPresenceCheck(
                $checks,
                "meta_description_presence_{$languageId}",
                'meta_description',
                trim((string) ($translation->meta_description ?? '')) !== '',
                "Falta la meta description{$prefix}; se recomienda completar hasta " . self::META_DESCRIPTION_MAX . ' caracteres.',
                $languageId,
                $languageCode,
                'warning',
            );

            $this->addLengthCheck(
                $checks,
                "meta_title_length_{$languageId}",
                'meta_title',
                (string) ($translation->meta_title ?? ''),
                self::META_TITLE_MAX,
                "El meta title{$prefix} supera el máximo recomendado de " . self::META_TITLE_MAX . ' caracteres.',
                $languageId,
                $languageCode,
            );
            $this->addLengthCheck(
                $checks,
                "meta_description_length_{$languageId}",
                'meta_description',
                (string) ($translation->meta_description ?? ''),
                self::META_DESCRIPTION_MAX,
                "La meta description{$prefix} supera el máximo de " . self::META_DESCRIPTION_MAX . ' caracteres.',
                $languageId,
                $languageCode,
            );

            $schemaData = trim((string) ($translation->schema_data ?? ''));
            if ($schemaData !== '' && json_decode($schemaData, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                $checks[] = [
                    'key' => "schema_data_{$languageId}",
                    'status' => 'fail',
                    'severity' => 'error',
                    'message' => "El JSON-LD{$prefix} no es válido.",
                    'field' => 'schema_data',
                    'language_id' => $languageId,
                    'language_code' => $languageCode,
                ];
            } else {
                $checks[] = [
                    'key' => "schema_data_{$languageId}",
                    'status' => 'pass',
                    'severity' => 'info',
                    'message' => "El JSON-LD{$prefix} está vacío o es válido.",
                    'field' => 'schema_data',
                    'language_id' => $languageId,
                    'language_code' => $languageCode,
                ];
            }

            $hasOgImage = trim((string) ($translation->og_image_url ?? '')) !== ''
                || (int) ($translation->og_image_file_id ?? 0) > 0;
            $checks[] = [
                'key' => "og_image_{$languageId}",
                'status' => $hasOgImage ? 'pass' : 'warning',
                'severity' => 'warning',
                'message' => $hasOgImage
                    ? "La imagen social{$prefix} está configurada."
                    : "Falta la imagen social{$prefix}; se recomienda para compartir la página.",
                'field' => 'og_image',
                'language_id' => $languageId,
                'language_code' => $languageCode,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param list<array<string, mixed>> $blocks
     */
    private function checkBlocks(array &$checks, array $blocks): void
    {
        $headingOwners = [];
        foreach ($blocks as $block) {
            $schema = $this->decodeArray($block['schema_definition'] ?? null);
            $presentation = is_array($schema['presentation'] ?? null) ? $schema['presentation'] : [];
            if (($presentation['owns_page_heading'] ?? false) === true) {
                $headingOwners[] = [
                    'id' => (int) ($block['id'] ?? 0),
                    'block_key' => (string) ($block['block_key'] ?? ''),
                ];
            }
        }

        $ownerCount = count($headingOwners);
        $checks[] = [
            'key' => 'page_heading_owner',
            'status' => $ownerCount === 1 ? 'pass' : 'fail',
            'severity' => 'error',
            'message' => $ownerCount === 0
                ? 'La página no tiene un bloque que declare ser dueño del H1.'
                : ($ownerCount > 1
                    ? 'La página tiene más de un bloque dueño del H1; debe existir exactamente uno.'
                    : 'La página tiene exactamente un bloque dueño del H1.'),
            'field' => 'blocks',
            'heading_owners' => $headingOwners,
        ];

        $checks[] = [
            'key' => 'active_blocks',
            'status' => $blocks !== [] ? 'pass' : 'fail',
            'severity' => 'error',
            'message' => $blocks !== []
                ? 'La página tiene bloques publicados.'
                : 'La página no tiene bloques publicados.',
            'field' => 'blocks',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param object $page
     * @param object|null $defaultLanguage
     * @param array<int, object> $translationByLanguage
     */
    private function checkSitemapAndRobots(array &$checks, object $page, ?object $defaultLanguage, array $translationByLanguage): void
    {
        $translation = $translationByLanguage[(int) ($defaultLanguage->id ?? 0)] ?? null;
        $robots = strtolower(trim((string) ($translation->robots ?? '')));
        $isIndexable = (bool) ($page->is_in_sitemap ?? false);
        $hasNoIndex = str_contains($robots, 'noindex');

        $checks[] = [
            'key' => 'sitemap_robots_consistency',
            'status' => ! $isIndexable || ! $hasNoIndex ? 'pass' : 'fail',
            'severity' => 'error',
            'message' => ! $isIndexable || ! $hasNoIndex
                ? 'La configuración de sitemap y robots es consistente.'
                : 'La página está en sitemap pero declara noindex.',
            'field' => 'robots',
            'language_id' => (int) ($defaultLanguage->id ?? 0),
            'language_code' => strtoupper((string) ($defaultLanguage->code ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'version' => self::VERSION,
            'heading_owner_count' => ['min' => 1, 'max' => 1],
            'meta_title' => ['max_length' => self::META_TITLE_MAX],
            'meta_description' => ['max_length' => self::META_DESCRIPTION_MAX],
            'sitemap_robots' => ['sitemap_pages_must_not_declare_noindex' => true],
            'json_ld' => ['must_be_valid_json_when_present' => true],
        ];
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function addPresenceCheck(
        array &$checks,
        string $key,
        string $field,
        bool $present,
        string $message,
        int $languageId,
        string $languageCode,
        string $missingSeverity = 'error',
    ): void {
        $checks[] = [
            'key' => $key,
            'status' => $present ? 'pass' : ($missingSeverity === 'warning' ? 'warning' : 'fail'),
            'severity' => $missingSeverity,
            'message' => $present ? 'Campo configurado.' : $message,
            'field' => $field,
            'language_id' => $languageId,
            'language_code' => $languageCode,
        ];
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function addLengthCheck(
        array &$checks,
        string $key,
        string $field,
        string $value,
        int $limit,
        string $message,
        int $languageId,
        string $languageCode,
    ): void {
        $length = mb_strlen(trim($value));
        $checks[] = [
            'key' => $key,
            'status' => $length <= $limit ? 'pass' : 'fail',
            'severity' => 'error',
            'message' => $length <= $limit ? "El campo está dentro del máximo de {$limit} caracteres." : $message,
            'field' => $field,
            'language_id' => $languageId,
            'language_code' => $languageCode,
            'length' => $length,
            'limit' => $limit,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The CI4 model return type is intentionally permissive for shared model
     * infrastructure. The quality evaluator only operates on hydrated
     * entities, so normalize that boundary once here.
     *
     * @param array<int, mixed> $rows
     * @return list<object>
     */
    private function objectRows(array $rows): array
    {
        return array_values(array_filter($rows, static fn (mixed $row): bool => is_object($row)));
    }
}
