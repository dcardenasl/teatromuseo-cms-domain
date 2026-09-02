<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\System;

use App\Services\PublicReadDiagnosticsService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/** Internal diagnostics consumed only by the Web app through X-App-Key. */
final class DiagnosticsController extends ApiController
{
    protected PublicReadDiagnosticsService $diagnosticsService;

    protected function resolveDefaultService(): PublicReadDiagnosticsService
    {
        $this->diagnosticsService = Services::publicReadDiagnostics();

        return $this->diagnosticsService;
    }

    public function publicRead(): ResponseInterface
    {
        return $this->handleRequest(function (array $data, mixed $context): mixed {
            return $this->diagnosticsService->report();
        });
    }
}
