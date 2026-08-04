<?php

declare(strict_types=1);

namespace App\Database\Seeds\Concerns;

/**
 * Canonical public URL segments used by CMS bootstrap data.
 *
 * Internal collection keys remain stable API identifiers; these values are
 * the visitor-facing, localized slugs only.
 */
final class TeatroMuseoPublicRoutes
{
    /** @return array{es: string, en: string, fr: string, pt: string} */
    public static function pageSlugs(string $pageType): array
    {
        return match ($pageType) {
            'events' => [
                'es' => 'cartelera',
                'en' => 'events',
                'fr' => 'programme',
                'pt' => 'eventos',
            ],
            'catalog_listing' => [
                'es' => 'museo/coleccion',
                'en' => 'museum/collection',
                'fr' => 'musee/collection',
                'pt' => 'museu/colecao',
            ],
            default => throw new \InvalidArgumentException('Unsupported public page type: ' . $pageType),
        };
    }

    /** @return array{es: string, en: string, fr: string, pt: string} */
    public static function collectionSlugs(string $collectionKey): array
    {
        return match ($collectionKey) {
            'cursos' => [
                'es' => 'teatroescuela',
                'en' => 'theaterschool',
                'fr' => 'theatreecole',
                'pt' => 'escola-de-teatro',
            ],
            default => [
                'es' => $collectionKey,
                'en' => $collectionKey,
                'fr' => $collectionKey,
                'pt' => $collectionKey,
            ],
        };
    }
}
