<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cms;

use App\Interfaces\Cms\CollectionServiceInterface;
use App\Interfaces\Cms\PublicReadNavigationReaderInterface;
use App\Interfaces\Cms\PublicReadSettingsReaderInterface;
use App\Services\Cms\PublicReadLayoutReader;
use dcardenasl\Ci4ApiCore\Support\ApiResult;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PublicReadLayoutReaderTest extends TestCase
{
    public function testComposesNavigationCollectionsAndSettingsIntoOneEnvelope(): void
    {
        $navigation = $this->createMock(PublicReadNavigationReaderInterface::class);
        $navigation->expects($this->once())->method('show')->with('es')->willReturn(new ApiResult([
            'ok' => true,
            'data' => ['main' => ['items' => []], 'footer' => null, 'legal' => null],
            'meta' => ['source_revision' => 'cms-navigation:2026-08-13T00:00:00Z:9'],
        ]));

        $settings = $this->createMock(PublicReadSettingsReaderInterface::class);
        $settings->expects($this->once())->method('show')->with('es')->willReturn(new ApiResult([
            'ok' => true,
            'data' => ['site_name' => 'TeatroMuseo'],
            'meta' => ['source_revision' => 'cms-settings:2026-08-13T00:00:00Z:3'],
        ]));

        $collections = $this->createMock(CollectionServiceInterface::class);
        $collections->expects($this->once())->method('listPublic')->with('es')->willReturn([
            ['id' => 1, 'collection_key' => 'teatroescuela'],
            ['id' => 2, 'collection_key' => 'museo'],
        ]);

        $reader = new PublicReadLayoutReader($navigation, $settings, $collections);
        $result = $reader->show('es');

        $this->assertTrue($result->body['ok']);
        $this->assertSame(['main' => ['items' => []], 'footer' => null, 'legal' => null], $result->body['data']['navigation']);
        $this->assertSame(['site_name' => 'TeatroMuseo'], $result->body['data']['settings']);
        $this->assertCount(2, $result->body['data']['collections']);
        $this->assertStringContainsString('cms-navigation:2026-08-13T00:00:00Z:9', $result->body['meta']['source_revision']);
        $this->assertStringContainsString('cms-settings:2026-08-13T00:00:00Z:3', $result->body['meta']['source_revision']);
        $this->assertStringContainsString('collections:2', $result->body['meta']['source_revision']);
        $this->assertSame('es', $result->body['meta']['locale']);
    }

    public function testFallsBackToEmptyMenusWhenNavigationDataIsMissing(): void
    {
        $navigation = $this->createMock(PublicReadNavigationReaderInterface::class);
        $navigation->method('show')->willReturn(new ApiResult(['ok' => false, 'data' => null, 'meta' => []]));

        $settings = $this->createMock(PublicReadSettingsReaderInterface::class);
        $settings->method('show')->willReturn(new ApiResult(['ok' => false, 'data' => null, 'meta' => []]));

        $collections = $this->createMock(CollectionServiceInterface::class);
        $collections->method('listPublic')->willReturn([]);

        $reader = new PublicReadLayoutReader($navigation, $settings, $collections);
        $result = $reader->show('en');

        $this->assertSame(['main' => null, 'footer' => null, 'legal' => null], $result->body['data']['navigation']);
        $this->assertSame([], $result->body['data']['settings']);
        $this->assertSame([], $result->body['data']['collections']);
    }
}
