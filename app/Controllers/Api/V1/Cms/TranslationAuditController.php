<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Cms;

use App\DTO\Request\Cms\TranslationAuditReportRequestDTO;
use App\Interfaces\Cms\TranslationAuditServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class TranslationAuditController extends ApiController
{
    protected TranslationAuditServiceInterface $auditService;

    protected function resolveDefaultService(): object
    {
        $this->auditService = Services::translationAuditService();
        return $this->auditService;
    }

    /**
     * Get overall translation completeness statistics per active language.
     */
    public function stats(): ResponseInterface
    {
        return $this->handleRequest(
            function (): array {
                $stats = $this->auditService->getOverallCompleteness();

                return ['status' => 'success', 'data' => $stats];
            }
        );
    }

    /**
     * Get a report of missing or incomplete translations across resources.
     */
    public function report(): ResponseInterface
    {
        return $this->handleRequest(
            function (TranslationAuditReportRequestDTO $dto): array {
                $result = $this->auditService->getMissingTranslationsReportPage(
                    $dto->filters(),
                    $dto->page,
                    $dto->limit
                );

                return ['status' => 'success', 'data' => $result['data'], 'meta' => $result['meta']];
            },
            TranslationAuditReportRequestDTO::class
        );
    }

    /**
     * Audit a single resource instance for translation completeness.
     */
    public function resource(string $type, int $id): ResponseInterface
    {
        return $this->handleRequest(
            function () use ($type, $id): array {
                $report = $this->auditService->auditResource($type, $id);

                return ['status' => 'success', 'data' => $report];
            }
        );
    }

    /**
     * Audit every block instance belonging to a single page/entry — the
     * contextual counterpart to resource(), used by the admin's block list
     * and "Ver" views instead of the sitewide report/stats.
     */
    public function owner(string $ownerType, int $ownerId): ResponseInterface
    {
        return $this->handleRequest(
            function () use ($ownerType, $ownerId): array {
                if (! in_array($ownerType, ['page', 'entry'], true)) {
                    throw new ValidationException(null, ['owner_type' => 'Must be "page" or "entry".']);
                }

                $report = $this->auditService->auditOwnerBlocks($ownerType, $ownerId);

                return ['status' => 'success', 'data' => $report];
            }
        );
    }
}
