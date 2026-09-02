<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Internal;

use App\Services\Cms\FileUsageService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Routes the Hub calls into (see HubSignatureFilter) to check whether this
 * domain references a Hub file before a destructive Hub file operation, and
 * to be told a file's cached metadata is stale. Never called by anything
 * other than the Hub — gated by the 'hubsignature' filter, not JWT/app-key.
 */
class InternalFileController extends Controller
{
    public function usage(int $hubFileId): ResponseInterface
    {
        /** @var FileUsageService $service */
        $service = Services::fileUsageService();
        $usages = $service->getUsagesByHubFileId($hubFileId);

        return Services::response()->setJSON([
            'status' => 'success',
            'data'   => [
                'in_use' => $usages !== [],
                'usages' => $usages,
            ],
        ]);
    }

    public function invalidateCache(int $hubFileId): ResponseInterface
    {
        Services::hubClient()->invalidateFileMetaCache($hubFileId);

        return Services::response()->setJSON([
            'status' => 'success',
            'data'   => ['invalidated' => true],
        ]);
    }
}
