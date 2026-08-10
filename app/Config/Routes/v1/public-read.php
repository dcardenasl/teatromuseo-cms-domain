<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->group('public-read', ['namespace' => '\App\Controllers\Api\V1\Cms', 'filter' => ['webappkey', 'throttle', 'correlationid', 'publicTelemetry']], static function ($routes): void {
    $routes->get('(:segment)/pages', 'PublicReadController::index/$1');
    $routes->get('(:segment)/pages/(:any)', 'PublicReadController::show/$1/$2');
    $routes->get('(:segment)/navigation', 'PublicReadController::navigation/$1');
    $routes->get('(:segment)/settings', 'PublicReadController::settings/$1');
    $routes->get('(:segment)/entries/(:segment)', 'PublicReadController::entries/$1/$2');
    $routes->get('(:segment)/entries/(:segment)/(:any)', 'PublicReadController::entry/$1/$2/$3');
});
