<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Cms;

use App\DTO\Request\Cms\BlockInstanceCreateRequestDTO;
use App\DTO\Request\Cms\BlockInstanceIndexRequestDTO;
use App\DTO\Request\Cms\BlockInstanceUpdateRequestDTO;
use App\Interfaces\Cms\BlockInstanceServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class BlockInstanceController extends ApiController
{
    protected BlockInstanceServiceInterface $blockInstanceService;

    /**
     * Owner type ('page'|'entry') for the current index() call, set by
     * indexForPage()/indexForEntry() from their own route parameter — never
     * inferred from the raw request URI.
     */
    private ?string $indexOwnerType = null;

    protected function resolveDefaultService(): BlockInstanceServiceInterface
    {
        $this->blockInstanceService = Services::blockInstanceService();

        return $this->blockInstanceService;
    }

    /**
     * Resolve the owner type for an existing block instance via the
     * service (the source of truth is the row's own `owner_type` column),
     * not by guessing from the request path.
     */
    private function ownerTypeForExistingInstance(int $id): string
    {
        $ownerType = $this->blockInstanceService->ownerTypeForInstance($id);
        if ($ownerType === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $ownerType;
    }

    private function requiresPermission(string $ownerType, string $action): string
    {
        return $ownerType === 'entry'
            ? "cms.entries.{$action}"
            : "cms.pages.{$action}";
    }

    protected array $statusCodes = [
        'store' => 201,
    ];

    public function indexForPage(int $pageId): ResponseInterface
    {
        $this->indexOwnerType = 'page';
        $this->blockInstanceService->setOwnerContext('page', $pageId);

        return $this->index();
    }

    public function indexForEntry(int $entryId): ResponseInterface
    {
        $this->indexOwnerType = 'entry';
        $this->blockInstanceService->setOwnerContext('entry', $entryId);

        return $this->index();
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest(
            function (BlockInstanceIndexRequestDTO $dto, SecurityContext $context): mixed {
                $ownerType = $this->indexOwnerType ?? 'page';
                if (!$context->hasPermission($this->requiresPermission($ownerType, 'read'))) {
                    throw new \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException(lang('Api.forbidden'));
                }
                return $this->blockInstanceService->index($dto, $context);
            },
            BlockInstanceIndexRequestDTO::class
        );
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest(
            function (BlockInstanceCreateRequestDTO $dto, SecurityContext $context): mixed {
                if (!$context->hasPermission($this->requiresPermission($dto->owner_type, 'write'))) {
                    throw new \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException(lang('Api.forbidden'));
                }
                return $this->blockInstanceService->store($dto, $context);
            },
            BlockInstanceCreateRequestDTO::class
        );
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            function (BlockInstanceUpdateRequestDTO $dto, SecurityContext $context) use ($id): mixed {
                $ownerType = $this->ownerTypeForExistingInstance($id);
                if (!$context->hasPermission($this->requiresPermission($ownerType, 'write'))) {
                    throw new \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException(lang('Api.forbidden'));
                }
                return $this->blockInstanceService->update($id, $dto, $context);
            },
            BlockInstanceUpdateRequestDTO::class
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(
            function (array $dto, SecurityContext $context) use ($id): mixed {
                $ownerType = $this->ownerTypeForExistingInstance($id);
                if (!$context->hasPermission($this->requiresPermission($ownerType, 'read'))) {
                    throw new \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException(lang('Api.forbidden'));
                }
                return $this->blockInstanceService->show($id, $context);
            }
        );
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(
            function (array $dto, SecurityContext $context) use ($id): mixed {
                $ownerType = $this->ownerTypeForExistingInstance($id);
                if (!$context->hasPermission($this->requiresPermission($ownerType, 'write'))) {
                    throw new \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException(lang('Api.forbidden'));
                }

                return $this->blockInstanceService->destroy($id, $context);
            }
        );
    }
}
