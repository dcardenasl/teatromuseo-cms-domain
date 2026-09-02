<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\Cms\CacheInvalidationClient;
use App\Libraries\Cms\CacheInvalidationOutbox;
use App\Libraries\Cms\CacheInvalidationOutboxDispatcher;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

final class CacheInvalidationOutboxTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testRollbackRemovesTheInvalidationEvent(): void
    {
        $db = Database::connect();
        $outbox = new CacheInvalidationOutbox($db);

        $db->transBegin();
        $outbox->append(['pages']);
        $db->transRollback();

        $this->assertSame(0, $db->table('cache_invalidation_outbox')->countAllResults());
    }

    public function testClaimIsSingleFlightAndCommittedEventCanBeAcknowledged(): void
    {
        $db = Database::connect();
        $outbox = new CacheInvalidationOutbox($db);
        $outbox->append(['pages', 'pages']);

        $claimed = $outbox->claim(10, 60);
        $this->assertCount(1, $claimed);
        $this->assertSame(['pages'], $claimed[0]['payload']['scopes']);
        $this->assertCount(0, $outbox->claim(10, 60));

        $this->assertTrue($outbox->markDispatched($claimed[0]['id'], $claimed[0]['lock_token']));
        $this->assertSame(0, $outbox->status()['pending']);
    }

    public function testDispatcherReleasesFailedDeliveryForRetry(): void
    {
        $db = Database::connect();
        $outbox = new CacheInvalidationOutbox($db);
        $outbox->append(['pages']);

        $result = (new CacheInvalidationOutboxDispatcher(
            $outbox,
            new FailingCacheInvalidationClient(),
        ))->dispatch(10);

        $this->assertSame(['claimed' => 1, 'dispatched' => 0, 'retried' => 1], $result);
        $this->assertSame(1, $outbox->status()['pending']);
    }
}

final class FailingCacheInvalidationClient extends CacheInvalidationClient
{
    public function invalidateNow(array $scopes, string $source = 'cms_automatic', array $locales = [], array $routes = []): bool
    {
        unset($scopes, $source, $locales, $routes);

        return false;
    }
}
