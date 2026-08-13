<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Interfaces\Cms\CollectionServiceInterface;
use App\Interfaces\Cms\PublicReadLayoutReaderInterface;
use App\Interfaces\Cms\PublicReadNavigationReaderInterface;
use App\Interfaces\Cms\PublicReadSettingsReaderInterface;
use App\Modules\PublicRead\Support\PublicReadEnvelope;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

/**
 * Aggregates the three PublicRead resources every page render needs,
 * regardless of which page it is, into one response — see ADR 006. This
 * class composes the existing readers/services as-is; it does not
 * duplicate their query logic and does not decide layout or rendering.
 */
final class PublicReadLayoutReader implements PublicReadLayoutReaderInterface
{
    public function __construct(
        private readonly PublicReadNavigationReaderInterface $navigationReader,
        private readonly PublicReadSettingsReaderInterface $settingsReader,
        private readonly CollectionServiceInterface $collectionService,
    ) {
    }

    public function show(string $locale): ApiResult
    {
        $navigation = $this->navigationReader->show($locale);
        $settings = $this->settingsReader->show($locale);
        $collections = $this->collectionService->listPublic($locale);

        $data = [
            'navigation' => $navigation->body['data'] ?? ['main' => null, 'footer' => null, 'legal' => null],
            'collections' => $collections,
            'settings' => $settings->body['data'] ?? [],
        ];

        $revision = sprintf(
            'cms-layout:%s|%s|collections:%d',
            (string) ($navigation->body['meta']['source_revision'] ?? 'empty'),
            (string) ($settings->body['meta']['source_revision'] ?? 'empty'),
            count($collections),
        );

        return PublicReadEnvelope::success(
            locale: $locale,
            data: $data,
            sourceRevision: $revision,
            meta: ['fields' => [], 'query' => ['resource' => 'layout']],
        );
    }
}
