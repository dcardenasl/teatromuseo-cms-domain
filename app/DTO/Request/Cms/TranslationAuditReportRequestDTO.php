<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Filters + pagination for TranslationAuditController::report(). Extracted
 * from the controller (LAYER-02) so query-string parsing/validation lives in
 * the DTO layer, matching this app's DTO-first convention, instead of the
 * controller reading `$this->request->getGet(...)` directly.
 */
#[OA\Schema(schema: 'TranslationAuditReportRequest')]
readonly class TranslationAuditReportRequestDTO extends BaseRequestDTO
{
    public ?int $languageId;
    public ?string $resource;
    public ?string $status;
    public ?string $search;
    public ?string $scope;
    public int $page;
    public int $limit;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'language_id' => 'permit_empty|is_natural_no_zero',
            'resource'    => 'permit_empty|string|max_length[100]',
            'status'      => 'permit_empty|string|max_length[50]',
            'search'      => 'permit_empty|string|max_length[100]',
            'scope'       => 'permit_empty|string|max_length[50]',
            'page'        => 'permit_empty|is_natural_no_zero',
            'limit'       => 'permit_empty|is_natural_no_zero|less_than[101]',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->languageId = isset($data['language_id']) && $data['language_id'] !== ''
            ? (int) $data['language_id']
            : null;
        $this->resource = $this->normalizeString($data['resource'] ?? null);
        $this->status = $this->normalizeString($data['status'] ?? null);
        $this->search = $this->normalizeString($data['search'] ?? null);
        $this->scope = $this->normalizeString($data['scope'] ?? null);
        $this->page = max(1, isset($data['page']) ? (int) $data['page'] : 1);
        $this->limit = min(100, max(10, isset($data['limit']) ? (int) $data['limit'] : 25));
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->languageId !== null) {
            $filters['language_id'] = $this->languageId;
        }
        if ($this->resource !== null) {
            $filters['resource'] = $this->resource;
        }
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        if ($this->search !== null) {
            $filters['search'] = $this->search;
        }
        if ($this->scope !== null) {
            $filters['scope'] = $this->scope;
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language_id' => $this->languageId,
            'resource'    => $this->resource,
            'status'      => $this->status,
            'search'      => $this->search,
            'scope'       => $this->scope,
            'page'        => $this->page,
            'limit'       => $this->limit,
        ];
    }
}
