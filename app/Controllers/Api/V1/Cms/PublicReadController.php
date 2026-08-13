<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Cms;

use App\DTO\Request\Cms\PublicReadEntryIndexRequestDTO;
use App\DTO\Request\Cms\PublicReadEntryShowRequestDTO;
use App\DTO\Request\Cms\PublicReadLocaleRequestDTO;
use App\DTO\Request\Cms\PublicReadPageRequestDTO;
use App\Interfaces\Cms\PublicReadEntryReaderInterface;
use App\Interfaces\Cms\PublicReadLayoutReaderInterface;
use App\Interfaces\Cms\PublicReadNavigationReaderInterface;
use App\Interfaces\Cms\PublicReadPageBootstrapReaderInterface;
use App\Interfaces\Cms\PublicReadPageReaderInterface;
use App\Interfaces\Cms\PublicReadSettingsReaderInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Http\ApiController;

final class PublicReadController extends ApiController
{
    /** @var list<string> */
    private const LISTING_FIELDS = ['id', 'page_type', 'title', 'excerpt', 'slug', 'localized_slugs', 'sitemap_priority', 'sitemap_changefreq', 'is_in_sitemap', 'updated_at'];

    /** @var list<string> */
    private const DETAIL_FIELDS = ['id', 'parent_id', 'collection_id', 'page_type', 'published_at', 'sort_order', 'sitemap_priority', 'sitemap_changefreq', 'is_in_sitemap', 'title', 'excerpt', 'meta_title', 'meta_description', 'canonical_url', 'robots', 'localized_slugs', 'blocks', 'updated_at'];

    /** @var list<string> */
    private const ENTRY_LISTING_FIELDS = ['id', 'collection_id', 'title', 'slug', 'excerpt', 'featured_image', 'is_featured', 'published_at', 'sort_order', 'sitemap_priority', 'sitemap_changefreq', 'is_in_sitemap', 'categories', 'tags', 'created_at', 'updated_at', 'listing_content'];

    /** @var list<string> */
    private const ENTRY_DETAIL_FIELDS = ['id', 'collection_id', 'title', 'slug', 'excerpt', 'featured_image', 'og_image', 'meta_title', 'meta_description', 'canonical_url', 'robots', 'schema_data', 'is_featured', 'published_at', 'sort_order', 'sitemap_priority', 'sitemap_changefreq', 'is_in_sitemap', 'categories', 'tags', 'translations', 'localized_slugs', 'blocks', 'created_at', 'updated_at'];

    private PublicReadPageReaderInterface $reader;

    private PublicReadNavigationReaderInterface $navigationReader;

    private PublicReadSettingsReaderInterface $settingsReader;

    private PublicReadEntryReaderInterface $entryReader;

    private PublicReadLayoutReaderInterface $layoutReader;

    private PublicReadPageBootstrapReaderInterface $pageBootstrapReader;

    protected function resolveDefaultService(): object
    {
        $this->reader = Services::publicReadPageReader();
        $this->navigationReader = Services::publicReadNavigationReader();
        $this->settingsReader = Services::publicReadSettingsReader();
        $this->entryReader = Services::publicReadEntryReader();
        $this->layoutReader = Services::publicReadLayoutReader();
        $this->pageBootstrapReader = Services::publicReadPageBootstrapReader();

        return $this->reader;
    }

    public function index(string $locale): ResponseInterface
    {
        return $this->handleRequest(
            function (PublicReadPageRequestDTO $dto, SecurityContext $context): mixed {
                return $this->reader->index($dto, $this->parseFields(self::LISTING_FIELDS));
            },
            PublicReadPageRequestDTO::class,
            ['locale' => $locale],
        );
    }

    public function show(string $locale, string $path): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadLocaleRequestDTO $dto): mixed => $this->reader->show($dto->locale, $path, $this->parseFields(self::DETAIL_FIELDS)),
            PublicReadLocaleRequestDTO::class,
            ['locale' => $locale],
        );
    }

    public function navigation(string $locale): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadLocaleRequestDTO $dto): mixed => $this->navigationReader->show($dto->locale),
            PublicReadLocaleRequestDTO::class,
            ['locale' => $locale],
        );
    }

    public function settings(string $locale): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadLocaleRequestDTO $dto): mixed => $this->settingsReader->show($dto->locale),
            PublicReadLocaleRequestDTO::class,
            ['locale' => $locale],
        );
    }

    /**
     * Composite: navigation + collections + settings in one response. See
     * ADR 006 — the same payload for every page in a locale, independent
     * of slug.
     */
    public function layout(string $locale): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadLocaleRequestDTO $dto): mixed => $this->layoutReader->show($dto->locale),
            PublicReadLocaleRequestDTO::class,
            ['locale' => $locale],
        );
    }

    /**
     * Composite: redirect check + page (with blocks) in one response. See
     * ADR 006 — the same pair Web's route resolver always requests
     * together for a given path.
     */
    public function pageBootstrap(string $locale, string $path): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadLocaleRequestDTO $dto): mixed => $this->pageBootstrapReader->show($dto->locale, $path, $this->parseFields(self::DETAIL_FIELDS)),
            PublicReadLocaleRequestDTO::class,
            ['locale' => $locale],
        );
    }

    public function entries(string $locale, string $collection): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadEntryIndexRequestDTO $dto): mixed => $this->entryReader->index($dto, $this->parseFields(self::ENTRY_LISTING_FIELDS)),
            PublicReadEntryIndexRequestDTO::class,
            ['locale' => $locale, 'collection' => $collection],
        );
    }

    public function entry(string $locale, string $collection, string $slug): ResponseInterface
    {
        return $this->handleRequest(
            fn (PublicReadEntryShowRequestDTO $dto): mixed => $this->entryReader->show($dto, $this->parseFields(self::ENTRY_DETAIL_FIELDS)),
            PublicReadEntryShowRequestDTO::class,
            ['locale' => $locale, 'collection' => $collection, 'slug' => $slug],
        );
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private function parseFields(array $allowed): array
    {
        $raw = $this->request->getGet('fields');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $requested = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $invalid = array_values(array_diff($requested, $allowed));
        if ($invalid !== []) {
            throw new BadRequestException(
                lang('Api.invalidFields'),
                ['fields' => array_map(static fn (string $field): string => lang('Api.unsupportedField', [$field]), $invalid)],
            );
        }

        return array_values(array_unique($requested));
    }
}
