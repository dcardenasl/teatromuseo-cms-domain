<?php

declare(strict_types=1);

namespace App\Filters;

use App\Support\PublicReadTelemetry;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PublicReadTelemetryFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RequestInterface
    {
        PublicReadTelemetry::begin($request);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        return PublicReadTelemetry::after($response);
    }
}
