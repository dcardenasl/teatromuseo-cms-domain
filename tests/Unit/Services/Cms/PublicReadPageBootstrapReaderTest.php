<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cms;

use App\Interfaces\Cms\PublicReadPageReaderInterface;
use App\Interfaces\Cms\RedirectServiceInterface;
use App\Services\Cms\PublicReadPageBootstrapReader;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Support\ApiResult;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PublicReadPageBootstrapReaderTest extends TestCase
{
    public function testReturnsBothRedirectAndPageWhenBothExist(): void
    {
        $redirects = $this->createMock(RedirectServiceInterface::class);
        $redirects->expects($this->once())
            ->method('resolvePublic')
            ->with(['nosotros'])
            ->willReturn(['new_url' => '/es/museo/coleccion', 'redirect_type' => 301]);

        $pages = $this->createMock(PublicReadPageReaderInterface::class);
        $pages->expects($this->once())
            ->method('show')
            ->with('es', 'nosotros', [])
            ->willReturn(new ApiResult([
                'ok' => true,
                'data' => ['id' => 5, 'title' => 'Nosotros'],
                'meta' => ['source_revision' => 'cms:2026-08-13T00:00:00Z:5'],
            ]));

        $reader = new PublicReadPageBootstrapReader($redirects, $pages);
        $result = $reader->show('es', 'nosotros');

        $this->assertSame(['new_url' => '/es/museo/coleccion', 'redirect_type' => 301], $result->body['data']['redirect']);
        $this->assertSame(['id' => 5, 'title' => 'Nosotros'], $result->body['data']['page']);
    }

    public function testNoRedirectIsNullNotAnErrorAndPageStillReturns(): void
    {
        $redirects = $this->createMock(RedirectServiceInterface::class);
        $redirects->method('resolvePublic')->willThrowException(new NotFoundException('not found'));

        $pages = $this->createMock(PublicReadPageReaderInterface::class);
        $pages->method('show')->willReturn(new ApiResult([
            'ok' => true,
            'data' => ['id' => 5, 'title' => 'Nosotros'],
            'meta' => ['source_revision' => 'cms:2026-08-13T00:00:00Z:5'],
        ]));

        $reader = new PublicReadPageBootstrapReader($redirects, $pages);
        $result = $reader->show('es', 'nosotros');

        $this->assertTrue($result->body['ok']);
        $this->assertNull($result->body['data']['redirect']);
        $this->assertSame(['id' => 5, 'title' => 'Nosotros'], $result->body['data']['page']);
        $this->assertStringContainsString('no-redirect', $result->body['meta']['source_revision']);
    }

    public function testPageNotFoundIsNullNotAnErrorForTheComposite(): void
    {
        $redirects = $this->createMock(RedirectServiceInterface::class);
        $redirects->method('resolvePublic')->willThrowException(new NotFoundException('not found'));

        $pages = $this->createMock(PublicReadPageReaderInterface::class);
        $pages->method('show')->willReturn(new ApiResult([
            'ok' => false,
            'data' => null,
            'meta' => ['source_revision' => 'cms:empty'],
        ], 404));

        $reader = new PublicReadPageBootstrapReader($redirects, $pages);
        $result = $reader->show('es', 'nunca-existe');

        // The composite always answers 200 with explicit nulls — callers
        // decide what a missing page/redirect means (try other resolution
        // strategies, 404, etc.), matching what Web's own
        // PageResolverService::parallelResolveRedirectAndPage() does today.
        $this->assertTrue($result->body['ok']);
        $this->assertNull($result->body['data']['redirect']);
        $this->assertNull($result->body['data']['page']);
    }

    public function testFieldsAreForwardedToThePageReader(): void
    {
        $redirects = $this->createMock(RedirectServiceInterface::class);
        $redirects->method('resolvePublic')->willThrowException(new NotFoundException('not found'));

        $pages = $this->createMock(PublicReadPageReaderInterface::class);
        $pages->expects($this->once())
            ->method('show')
            ->with('es', 'nosotros', ['id', 'title'])
            ->willReturn(new ApiResult(['ok' => true, 'data' => ['id' => 5], 'meta' => []]));

        $reader = new PublicReadPageBootstrapReader($redirects, $pages);
        $reader->show('es', 'nosotros', ['id', 'title']);
    }
}
