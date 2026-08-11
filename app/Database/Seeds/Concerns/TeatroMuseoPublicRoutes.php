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
            'about' => [
                'es' => 'nosotros',
                'en' => 'about',
                'fr' => 'a-propos',
                'pt' => 'sobre-nos',
            ],
            'history' => [
                'es' => 'historia',
                'en' => 'history',
                'fr' => 'histoire',
                'pt' => 'nossa-historia',
            ],
            'contact' => [
                'es' => 'contacto',
                'en' => 'contact',
                'fr' => 'contact',
                'pt' => 'contato',
            ],
            'events' => [
                'es' => 'cartelera',
                'en' => 'programming',
                'fr' => 'programmation',
                'pt' => 'programacao',
            ],
            'catalog_listing' => [
                'es' => 'museo/coleccion',
                'en' => 'museum/collection',
                'fr' => 'musee/collection',
                'pt' => 'museu/colecao',
            ],
            'press' => [
                'es' => 'prensa',
                'en' => 'press',
                'fr' => 'presse',
                'pt' => 'imprensa',
            ],
            'transparency' => [
                'es' => 'transparencia',
                'en' => 'transparency',
                'fr' => 'transparence',
                'pt' => 'transparencia',
            ],
            default => throw new \InvalidArgumentException('Unsupported public page type: ' . $pageType),
        };
    }

    /** @return array{es: string, en: string, fr: string, pt: string} */
    public static function collectionSlugs(string $collectionKey): array
    {
        return match ($collectionKey) {
            'noticias' => [
                'es' => 'noticias',
                'en' => 'news',
                'fr' => 'actualites',
                'pt' => 'noticias',
            ],
            'companias' => [
                'es' => 'companias',
                'en' => 'companies',
                'fr' => 'compagnies',
                'pt' => 'companhias',
            ],
            'personas' => [
                'es' => 'personas',
                'en' => 'people',
                'fr' => 'personnes',
                'pt' => 'pessoas',
            ],
            'obras' => [
                'es' => 'obras',
                'en' => 'works',
                'fr' => 'oeuvres',
                'pt' => 'obras',
            ],
            'videos' => [
                'es' => 'videos',
                'en' => 'videos',
                'fr' => 'videos',
                'pt' => 'videos',
            ],
            'festivales' => [
                'es' => 'festivales',
                'en' => 'festivals',
                'fr' => 'festivals',
                'pt' => 'festivais',
            ],
            'exposiciones' => [
                'es' => 'exposiciones',
                'en' => 'exhibitions',
                'fr' => 'expositions',
                'pt' => 'exposicoes',
            ],
            'teatroescuela' => [
                'es' => 'teatroescuela',
                'en' => 'theaterschool',
                'fr' => 'theatreecole',
                'pt' => 'escola-de-teatro',
            ],
            'editoriales' => [
                'es' => 'editorial',
                'en' => 'editorial',
                'fr' => 'editorial',
                'pt' => 'editorial',
            ],
            'prensa' => [
                'es' => 'prensa',
                'en' => 'press',
                'fr' => 'presse',
                'pt' => 'imprensa',
            ],
            'transparencia' => [
                'es' => 'transparencia',
                'en' => 'transparency',
                'fr' => 'transparence',
                'pt' => 'transparencia',
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
