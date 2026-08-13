<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\DTO\Request\Cms\PublicEntryIndexRequestDTO;
use App\DTO\Request\Cms\PublicEntryShowRequestDTO;
use App\DTO\Request\Cms\PublicReadEntryIndexRequestDTO;
use App\DTO\Request\Cms\PublicReadEntryShowRequestDTO;
use App\Interfaces\Cms\PublicReadEntryReaderInterface;
use App\Modules\PublicRead\Support\PublicReadEnvelope;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Support\ApiResult;
use dcardenasl\Ci4ApiCore\Support\RequestDtoFactory;

/** Adapter around the existing set-based entry read model, with a new contract. */
final class PublicReadEntryReader implements PublicReadEntryReaderInterface
{
    public function __construct(private readonly PublicEntryReader $reader, private readonly RequestDtoFactory $dtoFactory)
    {
    }

    public function index(PublicReadEntryIndexRequestDTO $request, array $fields): ApiResult
    {
        try {
            $legacy = $this->dtoFactory->make(PublicEntryIndexRequestDTO::class, [
                'lang' => $request->locale,
                'collection_key' => $request->collection,
                'page' => $request->page,
                'per_page' => $request->perPage,
                'category' => $request->category,
                'category_id' => $request->categoryId,
                'tag' => $request->tag,
                'q' => $request->search,
                'order_by' => $request->orderBy,
                'order_direction' => $request->orderDirection,
                'filter_by' => $request->filterBy,
                'filter_value' => $request->filterValue,
                'filter_operator' => $request->filterOperator,
                'include' => $request->rawInclude !== '' ? $request->rawInclude : null,
            ]);
            $result = $this->reader->listPublic($legacy, ['fields' => $fields])->toArray();
            $items = is_array($result['data'] ?? null) ? $result['data'] : [];
            $items = array_map(fn (mixed $item): array => $this->filter(is_array($item) ? $item : (array) $item, $fields), $items);
            $facets = $this->facets($result['data'] ?? []);
            return PublicReadEnvelope::success(
                $request->locale,
                $items,
                $this->revision($result['data'] ?? []),
                $request->page,
                $request->perPage,
                (int) ($result['total'] ?? count($items)),
                [
                    'fields' => $fields,
                    'facets' => $facets,
                    'collection' => $request->collection,
                    'query' => $request->toArray(),
                ],
            );
        } catch (NotFoundException) {
            return $this->notFound($request->locale);
        }
    }

    public function show(PublicReadEntryShowRequestDTO $request, array $fields): ApiResult
    {
        try {
            $legacy = $this->dtoFactory->make(PublicEntryShowRequestDTO::class, [
                'lang' => $request->locale,
                'collection_key' => $request->collection,
                'slug' => $request->slug,
                'preview' => $request->previewRequested ? '1' : null,
                'preview_expires' => $request->previewExpires,
                'preview_sig' => $request->previewSig,
            ]);
            $data = $this->reader->showPublic($legacy, ['fields' => $fields])->toArray();
            return PublicReadEnvelope::success(
                $request->locale,
                $this->filter($data, $fields),
                $this->revision([$data]),
                meta: ['fields' => $fields, 'collection' => $request->collection, 'query' => $request->toArray()],
            );
        } catch (NotFoundException) {
            return $this->notFound($request->locale);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function filter(array $data, array $fields): array
    {
        return $fields === [] ? $data : array_intersect_key($data, array_flip($fields));
    }

    /**
     * @param mixed $items
     * @return array<string, array<string, int>>
     */
    private function facets(mixed $items): array
    {
        $categories = [];
        $tags = [];
        foreach (is_array($items) ? $items : [] as $item) {
            foreach (is_array($item['categories'] ?? null) ? $item['categories'] : [] as $category) {
                $key = (string) ($category['id'] ?? $category['slug'] ?? '');
                if ($key !== '') {
                    $categories[$key] = ($categories[$key] ?? 0) + 1;
                }
            }
            foreach (is_array($item['tags'] ?? null) ? $item['tags'] : [] as $tag) {
                $key = (string) ($tag['id'] ?? $tag['slug'] ?? '');
                if ($key !== '') {
                    $tags[$key] = ($tags[$key] ?? 0) + 1;
                }
            }
        }
        return ['categories' => $categories, 'tags' => $tags];
    }

    /** @param array<int|string,mixed> $rows */
    private function revision(array $rows): string
    {
        $updated = '';
        $maxId = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $updated = max($updated, (string) ($row['updated_at'] ?? ''));
            $maxId = max($maxId, (int) ($row['id'] ?? 0));
        }
        return 'cms-entries:' . ($updated !== '' ? $updated : 'empty') . ':' . $maxId;
    }

    private function notFound(string $locale): ApiResult
    {
        return new ApiResult([
            'version' => 1,
            'ok' => false,
            'data' => null,
            'meta' => ['locale' => $locale, 'source_revision' => 'cms-entries:empty'],
            'source' => ['domain' => 'cms', 'state' => 'unavailable', 'stale' => false],
            'messages' => ['Entry not found.'],
        ], 404);
    }
}
