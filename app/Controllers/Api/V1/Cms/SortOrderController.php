<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Cms;

use App\DTO\Request\Cms\SortOrderBatchRequestDTO;
use App\Services\Cms\SortOrderBatchService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Http\ApiController;

final class SortOrderController extends ApiController
{
    protected SortOrderBatchService $sortOrderService;

    protected function resolveDefaultService(): SortOrderBatchService
    {
        $this->sortOrderService = Services::sortOrderBatchService();

        return $this->sortOrderService;
    }

    public function reorder(): ResponseInterface
    {
        return $this->handleRequest(
            function (SortOrderBatchRequestDTO $dto, SecurityContext $context): array {
                $permission = match ($dto->resource) {
                    'pages' => 'cms.pages.write',
                    'entries', 'block_instances' => $this->blockPermission($dto),
                    'categories' => 'cms.categories.write',
                    'languages' => 'cms.languages.write',
                    'menu_items' => 'cms.menus.write',
                    default => throw new AuthorizationException(lang('Api.forbidden')),
                };

                if (! $context->hasPermission($permission)) {
                    throw new AuthorizationException(lang('Api.forbidden'));
                }

                return $this->sortOrderService->reorder($dto);
            },
            SortOrderBatchRequestDTO::class,
        );
    }

    private function blockPermission(SortOrderBatchRequestDTO $request): string
    {
        if ($request->resource === 'entries') {
            return 'cms.entries.write';
        }

        return ($request->scope['owner_type'] ?? null) === 'entry'
            ? 'cms.entries.write'
            : 'cms.pages.write';
    }
}
