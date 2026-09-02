<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * LAYER-07: PublicTrackingController had zero test coverage. Also exercises
 * AnalyticsService::record() -> PageViewModel::record() end-to-end (the
 * write side of the LAYER-05 refactor).
 *
 * @internal
 */
final class PublicTrackingControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use WithWebAppKeyTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();
        $this->db->query('DELETE FROM `page_views`');
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    public function testTrackRecordsAPageViewAndReturnsNoContent(): void
    {
        $result = $this->post('/api/v1/public/track', [
            'url'          => '/es/obras',
            'page_title'   => 'Obras',
            'referrer'     => 'https://google.com/search?q=teatromuseo',
            'session_id'   => 'session-' . bin2hex(random_bytes(4)),
            'device_type'  => 'mobile',
            'browser'      => 'Chrome',
            'os'           => 'Android',
        ]);

        $result->assertStatus(204);
        $this->assertSame('', (string) $result->response()->getBody());

        $row = $this->db->table('page_views')->where('url', '/es/obras')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('Obras', $row['page_title']);
        $this->assertSame('google.com', $row['referrer_domain']);
        $this->assertSame('mobile', $row['device_type']);
    }

    public function testTrackWithMinimalFieldsDefaultsDeviceTypeToUnknown(): void
    {
        $result = $this->post('/api/v1/public/track', ['url' => '/es']);

        $result->assertStatus(204);

        $row = $this->db->table('page_views')->where('url', '/es')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('unknown', $row['device_type']);
        $this->assertNull($row['referrer_domain']);
    }

    public function testTrackWithoutUrlReturnsValidationError(): void
    {
        $result = $this->post('/api/v1/public/track', ['device_type' => 'desktop']);

        $result->assertStatus(422);
    }

    public function testRequestWithoutAppKeyIsRejected(): void
    {
        $this->withHeaders([]);

        $result = $this->post('/api/v1/public/track', ['url' => '/es']);

        $result->assertStatus(401);
    }
}
