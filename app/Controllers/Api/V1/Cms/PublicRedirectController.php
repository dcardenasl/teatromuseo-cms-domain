<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Cms;

use App\Interfaces\Cms\RedirectServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class PublicRedirectController extends ApiController
{
    protected RedirectServiceInterface $redirectService;

    protected function resolveDefaultService(): RedirectServiceInterface
    {
        $this->redirectService = Services::redirectService();

        return $this->redirectService;
    }

    /**
     * Resolve a redirect path.
     */
    public function resolve(string ...$segments): ResponseInterface
    {
        return $this->handleRequest(
            function (array $dto, SecurityContext $context) use ($segments): mixed {
                $resolution = $this->redirectService->resolvePublicWithMetadata(array_values($segments));
                $manual = $resolution['manual'];

                if ($manual !== null) {
                    $this->redirectService->recordPublicHit($manual['id'], $manual['hit_count']);
                }

                return $resolution['redirect'];
            }
        );
    }
}
