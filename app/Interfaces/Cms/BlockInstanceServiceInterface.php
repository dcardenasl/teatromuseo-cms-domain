<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use dcardenasl\Ci4ApiCore\Services\CrudServiceContract;

interface BlockInstanceServiceInterface extends CrudServiceContract
{
    /**
     * Scope subsequent index() calls to a specific owner (page or entry).
     * Must be called before index(); the context is consumed on first use.
     */
    public function setOwnerContext(string $ownerType, int $ownerId): void;

    /**
     * Resolve the owner type ('page'|'entry') of an existing block instance,
     * so callers (the controller's permission gate) can determine the right
     * permission code without inspecting the request URI. Returns null if
     * the instance doesn't exist.
     */
    public function ownerTypeForInstance(int $id): ?string;
}
