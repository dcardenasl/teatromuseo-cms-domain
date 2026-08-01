<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HTTP smoke test for the admin-only submissions/import route (used by the
 * legacy migration ETL to backfill historical form submissions). An
 * unauthenticated request returning 401 is enough to confirm the route is
 * registered and gated by domainauth, same convention as RedirectControllerTest.
 *
 * @internal
 */
final class FormSubmissionImportControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testImportRequiresAuth(): void
    {
        $result = $this->post('/api/v1/cms/submissions/import', [
            'form_key'  => 'contact',
            'form_data' => ['name' => 'Test'],
        ]);

        $result->assertStatus(401);
    }
}
