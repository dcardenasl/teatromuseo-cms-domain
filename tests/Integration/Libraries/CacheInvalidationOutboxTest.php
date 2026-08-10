<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\Cms\CacheInvalidationOutbox;
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
}
