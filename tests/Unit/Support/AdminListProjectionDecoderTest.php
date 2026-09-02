<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AdminListProjectionDecoder;
use PHPUnit\Framework\TestCase;

/** @internal */
final class AdminListProjectionDecoderTest extends TestCase
{
    public function testTruncatedHexTranslationIsIgnoredWithoutThrowing(): void
    {
        $translations = AdminListProjectionDecoder::translations(
            '1:4869:736c|2:ABC:736c',
            ['title', 'slug'],
        );

        $this->assertSame([
            ['language_id' => 1, 'title' => 'Hi', 'slug' => 'sl'],
        ], $translations);
    }
}
