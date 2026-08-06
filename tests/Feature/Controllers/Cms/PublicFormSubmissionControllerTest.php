<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * LAYER-07: PublicFormSubmissionController had zero test coverage.
 *
 * @internal
 */
final class PublicFormSubmissionControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use WithWebAppKeyTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private CmsFixtureFactory $fixtures;

    /** @var array{id:int,key:string,translations:list<array<string,mixed>>} */
    private array $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_form_submissions`');
        $this->db->query('DELETE FROM `cms_form_translations`');
        $this->db->query('DELETE FROM `cms_forms`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        // has_captcha defaults to 0 and notify_email is left null, so
        // create() neither requires a captcha token nor dispatches an email
        // notification job — no queue driver needed for this test.
        $this->form = $this->fixtures->form(overrides: ['form_key' => 'contact-' . bin2hex(random_bytes(3))]);
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    /**
     * The controller declares `$statusCodes = ['store' => 201]`, but
     * `handleRequest()` only consults that map when its target is a plain
     * string method name — passed a Closure (as here), `executeTarget()`
     * always resolves methodName to '', so the mapping never applies and
     * the response is 200, not 201. Same latent gap in every other
     * Closure-based "store" action across this app's controllers (flagged
     * separately, not a LAYER-07 fix); this asserts the actual observed
     * behavior rather than the code's apparent intent.
     */
    public function testStoreCreatesASubmissionAndReturns200(): void
    {
        $result = $this->post('/api/v1/public/submissions', [
            'form_key'  => $this->form['key'],
            'form_data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hola'],
        ]);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('success', $body['status']);
        $this->assertSame($this->form['key'], $body['data']['form_key']);
        $this->assertSame('new', $body['data']['status']);
        $this->assertSame(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hola'], $body['data']['form_data']);

        $row = $this->db->table('cms_form_submissions')->where('form_key', $this->form['key'])->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame($this->form['id'], (int) $row['form_id']);
        // ip_address/user_agent come from server-side additionalParams, not
        // client-supplied POST fields (see the controller's docblock).
        $this->assertNotSame('', (string) $row['ip_address']);
    }

    public function testStoreWithUnknownFormKeyStillCreatesASubmissionWithNullFormId(): void
    {
        $result = $this->post('/api/v1/public/submissions', [
            'form_key'  => 'does-not-exist',
            'form_data' => ['message' => 'hi'],
        ]);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        // FormSubmissionResponseDTO doesn't expose form_id at all — check
        // the persisted row instead, where form_id resolution actually
        // matters (email-notification dispatch keys off it).
        $this->assertSame('does-not-exist', $body['data']['form_key']);
        $row = $this->db->table('cms_form_submissions')->where('form_key', 'does-not-exist')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertNull($row['form_id']);
    }

    public function testStoreWithoutFormDataReturnsValidationError(): void
    {
        $result = $this->post('/api/v1/public/submissions', ['form_key' => $this->form['key']]);

        $result->assertStatus(422);
    }

    public function testRequestWithoutAppKeyIsRejected(): void
    {
        $this->withHeaders([]);

        $result = $this->post('/api/v1/public/submissions', [
            'form_key'  => $this->form['key'],
            'form_data' => ['message' => 'hi'],
        ]);

        $result->assertStatus(401);
    }
}
