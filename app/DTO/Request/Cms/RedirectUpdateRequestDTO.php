<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RedirectUpdateRequest')]
readonly class RedirectUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'old_path', type: 'string', nullable: true)]
    public ?string $old_path;
    #[OA\Property(description: 'new_url', type: 'string', nullable: true)]
    public ?string $new_url;
    #[OA\Property(description: 'redirect_type', type: 'integer', nullable: true)]
    public ?int $redirect_type;
    #[OA\Property(description: 'is_active', type: 'boolean', nullable: true)]
    public ?bool $is_active;
    #[OA\Property(description: 'hit_count', type: 'integer', nullable: true)]
    public ?int $hit_count;
    #[OA\Property(description: 'note', type: 'string', nullable: true)]
    public ?string $note;

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'old_path' => 'permit_empty|string|max_length[255]',
            'new_url' => 'permit_empty|string|max_length[255]',
            'redirect_type' => 'permit_empty|integer',
            'is_active' => 'permit_empty|boolean_like',
            'hit_count' => 'permit_empty|integer',
            'note' => 'permit_empty|string|max_length[255]',
        ];
    }

    /**
     * NOT NULL columns (old_path, new_url, redirect_type, is_active,
     * hit_count) never accept an explicit null — treated the same as
     * omitting the field, matching the DB constraint. The nullable column
     * (note) preserves an explicit null so it reaches toArray() and
     * actually clears the column — the bug this fixes is array_filter()
     * silently dropping every null, which made it impossible to ever
     * clear a nullable field via update.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->old_path = array_key_exists('old_path', $data) && $data['old_path'] !== null ? (string) $data['old_path'] : null;
        $this->new_url = array_key_exists('new_url', $data) && $data['new_url'] !== null ? (string) $data['new_url'] : null;
        $this->redirect_type = array_key_exists('redirect_type', $data) && $data['redirect_type'] !== null && $data['redirect_type'] !== '' ? (int) $data['redirect_type'] : null;
        $this->is_active = array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : null;
        $this->hit_count = array_key_exists('hit_count', $data) && $data['hit_count'] !== null && $data['hit_count'] !== '' ? (int) $data['hit_count'] : null;
        $this->note = array_key_exists('note', $data) && $data['note'] !== null && $data['note'] !== '' ? (string) $data['note'] : null;

        $mappedFields = [];
        if ($this->old_path !== null) {
            $mappedFields['old_path'] = $this->old_path;
        }
        if ($this->new_url !== null) {
            $mappedFields['new_url'] = $this->new_url;
        }
        if ($this->redirect_type !== null) {
            $mappedFields['redirect_type'] = $this->redirect_type;
        }
        if ($this->is_active !== null) {
            $mappedFields['is_active'] = $this->is_active;
        }
        if ($this->hit_count !== null) {
            $mappedFields['hit_count'] = $this->hit_count;
        }
        if (array_key_exists('note', $data)) {
            $mappedFields['note'] = $this->note;
        }

        $this->mappedFields = $mappedFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mappedFields;
    }
}
