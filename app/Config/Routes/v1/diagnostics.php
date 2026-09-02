<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('diagnostics', ['filter' => ['webappkey', 'throttle:10,60']], function ($routes): void {
    $routes->get('public-read', '\App\Controllers\Api\V1\System\DiagnosticsController::publicRead');
});
