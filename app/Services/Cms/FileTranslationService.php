<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Entities\FileTranslationEntity;
use App\Interfaces\Cms\FileTranslationServiceInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

/**
 * @extends BaseCrudService<FileTranslationEntity>
 */
class FileTranslationService extends BaseCrudService implements FileTranslationServiceInterface
{
    /**
     * @param RepositoryInterface<FileTranslationEntity> $fileTranslationRepository
     */
    public function __construct(
        RepositoryInterface $fileTranslationRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($fileTranslationRepository, $responseMapper);
    }

    /**
     * Reject the composite uniqueness conflict before MySQL reports it.
     *
     * The generic core transaction wrapper observes a failed duplicate-key
     * statement as a failed transaction and, on long-lived connections, can
     * carry that status into the next request.  A domain validation keeps the
     * expected 422 response deterministic and avoids poisoning the connection
     * for subsequent CRUD operations.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        $model = $this->repository->getModel();
        $model->builder()->resetQuery();
        $existing = $model
            ->where('file_id', (int) ($data['file_id'] ?? 0))
            ->where('language_id', (int) ($data['language_id'] ?? 0))
            ->first();
        $model->builder()->resetQuery();

        if ($existing !== null) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                ['language_id' => lang('Api.invalidTranslation')]
            );
        }

        return $data;
    }

    /**
     * FileTranslationController::index() merges `file_id` from the route
     * into FileTranslationIndexRequestDTO's raw data, and the DTO does
     * expose it via toArray() — but BaseRepository::paginateCriteria()
     * only ever reads `criteria['filter']` (an array), `criteria['sort']`,
     * and `criteria['search']`; a bare top-level `file_id` key was being
     * silently ignored, so `GET /cms/files/{fileId}/translations` returned
     * every file's translations instead of just this one's (found and
     * fixed under LAYER-07, 2026-08-06 — see
     * tests/Feature/Controllers/Cms/FileTranslationControllerTest.php).
     * FileTranslationModel::$filterableFields already allows `file_id`, so
     * folding it into `criteria['filter']` here is enough for
     * QueryBuilder::filter() to apply it.
     *
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>
     */
    protected function applyQueryOptions(array $criteria): array
    {
        $criteria = parent::applyQueryOptions($criteria);

        if (isset($criteria['file_id']) && $criteria['file_id'] !== '') {
            $filter = is_array($criteria['filter'] ?? null) ? $criteria['filter'] : [];
            $filter['file_id'] = $criteria['file_id'];
            $criteria['filter'] = $filter;
        }

        return $criteria;
    }
}
