<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Cms;

use App\Libraries\Cms\SlugGenerator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SlugGeneratorTest extends CIUnitTestCase
{
    private SlugGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SlugGenerator();
    }

    public function testSlugifyRemovesAccentsWithoutLeavingBrokenSeparators(): void
    {
        $this->assertSame('subete-al-escenario', $this->generator->slugify('Súbete al escenario'));
    }

    public function testUniquifySkipsTakenCandidates(): void
    {
        $taken = [
            'subete-al-escenario' => true,
            'subete-al-escenario-2' => true,
        ];

        $slug = $this->generator->uniquify(
            'subete-al-escenario',
            static fn (string $candidate): bool => ! isset($taken[$candidate])
        );

        $this->assertSame('subete-al-escenario-3', $slug);
    }
}
