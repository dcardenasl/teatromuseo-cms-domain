<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Interfaces\Cms\PublicReadPageBootstrapReaderInterface;
use App\Interfaces\Cms\PublicReadPageReaderInterface;
use App\Interfaces\Cms\RedirectServiceInterface;
use App\Modules\PublicRead\Support\PublicReadEnvelope;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

/**
 * Aggregates the redirect check and the page-by-path lookup Web's route
 * resolver always requests together for a given path — see ADR 006. A
 * missing redirect is represented as `redirect: null` in a 200 response,
 * not as this endpoint's own 404: the caller (Web) still needs the page
 * lookup result even when there is no redirect, and still needs to try
 * other resolution strategies (collection entry, fallback listing) when
 * there is neither. Composes the existing reader/service as-is; does not
 * duplicate their query logic.
 */
final class PublicReadPageBootstrapReader implements PublicReadPageBootstrapReaderInterface
{
    public function __construct(
        private readonly RedirectServiceInterface $redirectService,
        private readonly PublicReadPageReaderInterface $pageReader,
    ) {
    }

    public function show(string $locale, string $path, array $fields = []): ApiResult
    {
        $redirect = $this->resolveRedirect($path);
        $pageResult = $this->pageReader->show($locale, $path, $fields);
        $pageFound = (bool) ($pageResult->body['ok'] ?? false);

        $data = [
            'redirect' => $redirect,
            'page' => $pageFound ? $pageResult->body['data'] : null,
        ];

        $revision = sprintf(
            'cms-page-bootstrap:%s|%s',
            (string) ($pageResult->body['meta']['source_revision'] ?? 'empty'),
            $redirect !== null ? 'redirect' : 'no-redirect',
        );

        return PublicReadEnvelope::success(
            locale: $locale,
            data: $data,
            sourceRevision: $revision,
            meta: ['fields' => $fields, 'query' => ['path' => $path]],
        );
    }

    /** @return array{new_url: string, redirect_type: int}|null */
    private function resolveRedirect(string $path): ?array
    {
        $segments = [trim($path, '/')];
        try {
            return $this->redirectService->resolvePublic($segments);
        } catch (NotFoundException) {
            return null;
        }
    }
}
