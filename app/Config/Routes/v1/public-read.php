<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->group('public-read', ['namespace' => '\App\Controllers\Api\V1\Cms', 'filter' => ['webappkey', 'throttle', 'correlationid', 'publicTelemetry']], static function ($routes): void {
    $routes->get('(:segment)/pages', 'PublicReadController::index/$1');
    // Public page slugs may be hierarchical (for example
    // `museo/coleccion`), so the path placeholder must include slashes.
    $routes->get('(:segment)/pages/(.+)', 'PublicReadController::show/$1/$2');
    $routes->get('(:segment)/navigation', 'PublicReadController::navigation/$1');
    $routes->get('(:segment)/settings', 'PublicReadController::settings/$1');
    $routes->get('(:segment)/entries/(:segment)', 'PublicReadController::entries/$1/$2');
    $routes->get('(:segment)/entries/(:segment)/(:any)', 'PublicReadController::entry/$1/$2/$3');
    // Composite bootstrap endpoints — see ADR 006. `layout` is slug-independent
    // (same payload for every page); `page-bootstrap` mirrors `pages/{path}`'s
    // hierarchical-slug support, so its placeholder also needs to allow slashes.
    $routes->get('(:segment)/layout', 'PublicReadController::layout/$1');
    $routes->get('(:segment)/page-bootstrap/(.+)', 'PublicReadController::pageBootstrap/$1/$2');
});
