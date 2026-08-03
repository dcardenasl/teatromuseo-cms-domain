<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

/**
 * Deterministic slugifier for CMS routing slugs.
 */
final class SlugGenerator
{
    public function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        $ascii = null;
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $ascii = preg_replace('/\p{Mn}+/u', '', $normalized);
            }
        }

        if (! is_string($ascii) || $ascii === '') {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        }

        if (! is_string($ascii) || $ascii === '') {
            $ascii = $value;
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '-', $ascii);
        if (! is_string($slug)) {
            return '';
        }

        return trim(mb_strtolower($slug), '-');
    }

    /**
     * Returns a slug that is guaranteed to be unique according to the given callback.
     *
     * @param callable(string): bool $isAvailable
     */
    public function uniquify(string $baseSlug, callable $isAvailable, int $maxAttempts = 999): string
    {
        $baseSlug = trim($baseSlug, '-');
        if ($baseSlug === '') {
            return '';
        }

        if ($isAvailable($baseSlug)) {
            return $baseSlug;
        }

        for ($suffix = 2; $suffix <= $maxAttempts; $suffix++) {
            $candidate = $baseSlug . '-' . $suffix;
            if ($isAvailable($candidate)) {
                return $candidate;
            }
        }

        return $baseSlug . '-' . bin2hex(random_bytes(4));
    }
}
