<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use dcardenasl\Ci4ApiCore\Services\CrudServiceContract;

interface RedirectServiceInterface extends CrudServiceContract
{
    /**
     * @param list<string> $segments
     * @return array{new_url: string, redirect_type: int}
     */
    public function resolvePublic(array $segments): array;

    /**
     * @param list<string> $segments
     * @return array{
     *     redirect: array{new_url: string, redirect_type: int},
     *     manual: array{id: int, hit_count: int}|null
     * }
     */
    public function resolvePublicWithMetadata(array $segments): array;

    public function recordPublicHit(int $redirectId, int $currentHitCount): void;
}
