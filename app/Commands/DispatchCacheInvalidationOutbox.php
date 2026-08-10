<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

final class DispatchCacheInvalidationOutbox extends BaseCommand
{
    protected $group = 'Cache';
    protected $name = 'cache:dispatch-outbox';
    protected $description = 'Deliver committed public-cache invalidation events to Web.';
    protected $usage = 'cache:dispatch-outbox [--limit N]';
    protected $options = ['--limit' => 'Maximum number of events to deliver (default: 20).'];

    public function run(array $params): void
    {
        $limit = max(1, min(100, (int) (CLI::getOption('limit') ?: 20)));
        $result = Services::cacheInvalidationOutboxDispatcher(false)->dispatch($limit);
        CLI::write(sprintf(
            'Cache invalidation outbox: claimed=%d dispatched=%d retried=%d',
            $result['claimed'],
            $result['dispatched'],
            $result['retried'],
        ), $result['retried'] === 0 ? 'green' : 'yellow');
    }
}
