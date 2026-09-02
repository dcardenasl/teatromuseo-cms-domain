<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Cms\BlockNavigationResolver;
use App\Libraries\Cms\SlugRouter;
use CodeIgniter\Test\CIUnitTestCase;

final class BlockNavigationResolverTest extends CIUnitTestCase
{
    public function testUnsupportedSourceIsReportedWithoutInventingAUrl(): void
    {
        $resolver = new BlockNavigationResolver(new SlugRouter());

        $result = $resolver->resolve(
            ['source_type' => 'unknown'],
            'es',
            ['source' => 'block_config', 'target' => 'collection_index'],
        );

        $this->assertSame('unsupported_source', $result['status']);
        $this->assertNull($result['target_type']);
        $this->assertNull($result['target_id']);
        $this->assertNull($result['url']);
    }

    public function testEmptyAutoSourceIsReportedAsUnsupported(): void
    {
        $resolver = new BlockNavigationResolver(new SlugRouter());

        $result = $resolver->resolve(['source_type' => 'auto'], 'en');

        $this->assertSame('navigation_not_declared', $result['status']);
        $this->assertNull($result['url']);
    }
}
