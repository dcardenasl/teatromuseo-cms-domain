<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * CMS domain settings that describe the consuming public site.
 *
 * The CMS does not own the site's routes; when it must emit one (e.g. the
 * event_listing menu link type), the path comes from here so a site with a
 * different URL scheme only changes configuration, never service code.
 */
class Cms extends BaseConfig
{
    /**
     * Public path the event_listing menu link type points at.
     * Override with cms.eventListingPath in .env.
     */
    public string $eventListingPath = '/cartelera';

    public function __construct()
    {
        parent::__construct();

        $configured = trim((string) env('cms.eventListingPath', ''));
        if ($configured !== '') {
            $this->eventListingPath = '/' . ltrim($configured, '/');
        }
    }
}
