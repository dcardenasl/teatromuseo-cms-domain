<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Query-string params for PublicPageController::show(). Extracted from the
 * controller (LAYER-02) so `?preview=1&preview_expires=...&preview_sig=...`
 * parsing/typing lives in the DTO layer instead of raw `$this->request->getGet()`
 * calls in the closure. Stays a pure data object — verifying the signature
 * against lang+slug (PreviewToken::verify()) is left to the controller,
 * which is the same class of HTTP-boundary security check the base
 * ApiController itself performs for JWTs, not domain business logic.
 */
#[OA\Schema(schema: 'PublicPageShowRequest')]
readonly class PublicPageShowRequestDTO extends BaseRequestDTO
{
    public bool $previewRequested;
    public ?string $previewExpires;
    public ?string $previewSig;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'preview'         => 'permit_empty|in_list[0,1]',
            'preview_expires' => 'permit_empty|string',
            'preview_sig'     => 'permit_empty|string',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->previewRequested = ($data['preview'] ?? null) === '1';
        $this->previewExpires = isset($data['preview_expires']) && is_string($data['preview_expires'])
            ? $data['preview_expires']
            : null;
        $this->previewSig = isset($data['preview_sig']) && is_string($data['preview_sig'])
            ? $data['preview_sig']
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preview'         => $this->previewRequested,
            'preview_expires' => $this->previewExpires,
            'preview_sig'     => $this->previewSig,
        ];
    }
}
