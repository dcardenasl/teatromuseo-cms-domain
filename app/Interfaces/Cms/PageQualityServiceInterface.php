<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

interface PageQualityServiceInterface
{
    /**
     * Build the editorial and SEO readiness report for a page.
     *
     * The report is deliberately produced by the CMS domain so every
     * consumer (Admin, Web and future clients) reads the same policy.
     *
     * @return array<string, mixed>
     */
    public function analyze(int $pageId): array;
}
