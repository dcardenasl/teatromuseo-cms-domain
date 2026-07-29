<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Entities\LanguageEntity;
use App\Entities\PageEntity;
use App\Libraries\Cms\BlockInstanceSerializer;
use App\Libraries\Cms\CmsEnums;
use App\Libraries\Cms\SlugRouter;
use App\Libraries\Cms\TranslationResolver;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;

/**
 * Read-optimized public (unauthenticated) page queries: sitemap listing and
 * single-page lookup by language + slug, with block serialization and
 * localized-slug resolution.
 *
 * Extracted from PublicPageController, which used to run all of this inline.
 * Composed by PageService for listPublic()/showPublic(), mirroring the
 * EntryService/PublicEntryReader split.
 *
 * Preview-token verification itself stays at the controller/HTTP boundary
 * (it reads raw GET params) — this class only receives the already-verified
 * `$preview` boolean and applies it to slug resolution / publish-status gating.
 */
class PublicPageReader
{
    /**
     * @param RepositoryInterface<PageEntity> $pageRepository
     * @param RepositoryInterface<LanguageEntity> $languageRepository
     */
    public function __construct(
        private readonly RepositoryInterface $pageRepository,
        private readonly RepositoryInterface $languageRepository,
        private readonly SlugRouter $slugRouter,
        private readonly TranslationResolver $translationResolver,
        private readonly BlockInstanceSerializer $blockInstanceSerializer,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublic(string $lang): array
    {
        /** @var list<PageEntity> $pages */
        $pages = $this->pageRepository->getModel()
            ->where('status', 'published')
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $result = [];

        foreach ($pages as $page) {
            if (!$page instanceof PageEntity) {
                continue;
            }

            $slug = $this->slugRouter->resolveSlug($lang, 'page', (int) $page->id);
            if (!$slug) {
                continue;
            }

            $translation = $this->translationResolver->resolve('page', (int) $page->id, $lang);

            $result[] = [
                'slug'               => $slug,
                'title'              => $translation['title'] ?? '',
                'sitemap_priority'   => $page->sitemap_priority ?? 0.5,
                'sitemap_changefreq' => $page->sitemap_changefreq ?? 'weekly',
                'is_in_sitemap'      => $page->is_in_sitemap ?? true,
                'updated_at'         => $page->updated_at,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function showPublic(string $lang, string $slug, bool $preview): array
    {
        // Slug resolution itself is published-only (findPageBySlugAndParent);
        // $preview must already be a verified signed-preview flag by the time
        // it reaches here to be allowed to bypass it.
        $pageId = $this->slugRouter->resolve($lang, 'page', $slug, $preview);

        if ($pageId === null) {
            throw new NotFoundException(lang('Pages.not_found'));
        }

        /** @var PageEntity|null $page */
        $page = $this->pageRepository->find($pageId);
        if ($page === null || (!$preview && $page->status !== 'published')) {
            throw new NotFoundException(lang('Pages.not_found'));
        }

        // Singleton template pages only render with a runtime context injected
        // by the public site (via the by-type endpoint). Serving them by slug
        // would expose an empty shell page — signed previews stay allowed so
        // editors can still inspect the template from the admin.
        if (! $preview && in_array((string) $page->page_type, CmsEnums::PAGE_TEMPLATE_TYPES, true)) {
            throw new NotFoundException(lang('Pages.not_found'));
        }

        return $this->presentPage($page, (int) $page->id, $lang);
    }

    /**
     * Resolve a singleton template page by its page_type. The database
     * enforces the singleton via the type_singleton generated column, so the
     * type — not a magic slug — is the stable contract with the public site.
     *
     * @return array<string, mixed>
     */
    public function showPublicByType(string $lang, string $type): array
    {
        if (! in_array($type, CmsEnums::PAGE_TEMPLATE_TYPES, true)) {
            throw new NotFoundException(lang('Pages.not_found'));
        }

        /** @var PageEntity|null $page */
        $page = $this->pageRepository->getModel()
            ->where('page_type', $type)
            ->where('status', 'published')
            ->first();

        if (! $page instanceof PageEntity) {
            throw new NotFoundException(lang('Pages.not_found'));
        }

        return $this->presentPage($page, (int) $page->id, $lang);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPage(PageEntity $page, int $pageId, string $lang): array
    {
        $translation = $this->translationResolver->resolve('page', $pageId, $lang);
        $blocks = $this->blockInstanceSerializer->forContent('page', $pageId, $lang);

        $data = array_merge($page->toArray(), $translation);
        $data['blocks'] = $blocks;

        $localizedSlugs = [];
        /** @var list<LanguageEntity> $languages */
        $languages = $this->languageRepository->getModel()->where('is_active', 1)->findAll();
        foreach ($languages as $language) {
            if ($language instanceof LanguageEntity) {
                $localizedSlugs[$language->code] = $this->slugRouter->resolveSlug($language->code, 'page', $pageId);
            }
        }
        $data['localized_slugs'] = $localizedSlugs;

        return $data;
    }
}
