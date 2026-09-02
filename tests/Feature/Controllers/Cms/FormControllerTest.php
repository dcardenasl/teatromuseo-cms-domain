<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use App\Libraries\Hub\HubClient;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use Tests\Support\ApiTestCase;
use Tests\Support\Fixtures\CmsFixtureFactory;

/**
 * Authenticated HTTP coverage for the form aggregate and its field sub-resource.
 *
 * @internal
 */
final class FormControllerTest extends ApiTestCase
{
    private CmsFixtureFactory $fixtures;

    /** @var list<array{id:int,code:string,name:string,is_default:bool}> */
    private array $languages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_form_field_translations`');
        $this->db->query('DELETE FROM `cms_form_fields`');
        $this->db->query('DELETE FROM `cms_form_submissions`');
        $this->db->query('DELETE FROM `cms_form_translations`');
        $this->db->query('DELETE FROM `cms_forms`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(2);
        $this->authenticate();
    }

    private function authenticate(): void
    {
        $stub = new class (new IntrospectResult(
            valid: true,
            uid: 1,
            permissions: ['cms.forms.read', 'cms.forms.write', 'cms.forms.admin'],
            exp: time() + 3600,
            error: null
        )) extends HubClient {
            public function __construct(private readonly IntrospectResult $result)
            {
            }

            public function introspect(string $token): IntrospectResult
            {
                return $this->result;
            }
        };

        Services::injectMock('hubClient', $stub);
        $this->setTestRequestHeaders(['Authorization' => 'Bearer fake-test-token']);
    }

    /** @param array<string, mixed> $overrides */
    private function createForm(array $overrides = []): int
    {
        $formKey = (string) ($overrides['form_key'] ?? $this->fixtures->slug('form'));
        $payload = array_replace([
            'form_key' => $formKey,
            'is_active' => true,
            'has_captcha' => false,
            'notify_email' => 'museum@example.test',
            'translations' => [[
                'language_id' => (string) $this->languages[0]['id'],
                'name' => $this->fixtures->text('form-name'),
                'description' => $this->fixtures->text('form-description'),
                'submit_label' => 'Enviar',
            ]],
        ], $overrides);

        $result = $this->post('/api/v1/cms/forms', $payload);
        $result->assertStatus(200);
        $body = json_decode((string) $result->response()->getBody(), true);

        return (int) $body['data']['id'];
    }

    public function testFormAndFieldLifecycle(): void
    {
        $formId = $this->createForm();

        $show = $this->get("/api/v1/cms/forms/{$formId}");
        $show->assertStatus(200);
        $showBody = json_decode((string) $show->response()->getBody(), true);
        $this->assertStringStartsWith('fixture-tests-feature-co-', $showBody['data']['form_key']);
        $this->assertCount(1, $showBody['data']['translations']);
        $this->assertSame([], $showBody['data']['fields']);

        $firstField = $this->withBodyFormat('json')->post("/api/v1/cms/forms/{$formId}/fields", [
            'field_key' => 'message',
            'field_type' => 'textarea',
            'display_order' => 0,
            'is_required' => true,
            'translations' => [[
                'language_id' => (string) $this->languages[0]['id'],
                'label' => 'Mensaje',
                'placeholder' => 'Escribe tu mensaje',
            ]],
        ]);
        $firstField->assertStatus(200);
        $firstFieldBody = json_decode((string) $firstField->response()->getBody(), true);
        $firstFieldId = (int) $firstFieldBody['data']['id'];

        $secondField = $this->withBodyFormat('json')->post("/api/v1/cms/forms/{$formId}/fields", [
            'field_key' => 'visit_type',
            'field_type' => 'select',
            'options' => ['guided', 'self-guided'],
            'display_order' => 1,
            'translations' => [[
                'language_id' => (string) $this->languages[0]['id'],
                'label' => 'Tipo de visita',
                'option_labels' => ['guided' => 'Guiada', 'self-guided' => 'Autoguiada'],
            ]],
        ]);
        $secondField->assertStatus(200);
        $secondFieldBody = json_decode((string) $secondField->response()->getBody(), true);
        $secondFieldId = (int) $secondFieldBody['data']['id'];
        $this->assertSame(['guided', 'self-guided'], $secondFieldBody['data']['options']);

        $fields = $this->get("/api/v1/cms/forms/{$formId}/fields");
        $fields->assertStatus(200);
        $fieldsBody = json_decode((string) $fields->response()->getBody(), true);
        $this->assertCount(2, $fieldsBody['data']);

        $updateField = $this->withBodyFormat('json')->put("/api/v1/cms/forms/{$formId}/fields/{$secondFieldId}", [
            'options' => ['guided'],
            'display_order' => 4,
            'translations' => [[
                'language_id' => (string) $this->languages[0]['id'],
                'label' => 'Tipo de visita actualizado',
                'option_labels' => ['guided' => 'Guiada'],
            ]],
        ]);
        $updateField->assertStatus(200);
        $updateFieldBody = json_decode((string) $updateField->response()->getBody(), true);
        $this->assertSame(['guided'], $updateFieldBody['data']['options']);
        $this->assertSame(4, $updateFieldBody['data']['display_order']);

        $reorder = $this->withBodyFormat('json')->patch("/api/v1/cms/forms/{$formId}/fields/reorder", [
            'ordered_ids' => [$secondFieldId, $firstFieldId],
        ]);
        $reorder->assertStatus(200);

        $deleteSecondField = $this->delete("/api/v1/cms/forms/{$formId}/fields/{$secondFieldId}");
        $deleteSecondField->assertStatus(200);
        $deleteFirstField = $this->delete("/api/v1/cms/forms/{$formId}/fields/{$firstFieldId}");
        $deleteFirstField->assertStatus(200);

        $updateForm = $this->withBodyFormat('json')->put("/api/v1/cms/forms/{$formId}", [
            'is_active' => false,
            'autoreply_enabled' => true,
            'autoreply_email_field' => 'email',
            'translations' => [[
                'language_id' => (string) $this->languages[0]['id'],
                'name' => 'Formulario actualizado',
                'submit_label' => 'Enviar ahora',
            ]],
        ]);
        $updateForm->assertStatus(200);
        $updateFormBody = json_decode((string) $updateForm->response()->getBody(), true);
        $this->assertFalse($updateFormBody['data']['is_active']);
        $this->assertTrue($updateFormBody['data']['autoreply_enabled']);

        $deleteForm = $this->delete("/api/v1/cms/forms/{$formId}");
        $deleteForm->assertStatus(200);

        $missing = $this->get("/api/v1/cms/forms/{$formId}");
        $missing->assertStatus(404);
    }

    public function testListProjectionSupportsSearchAndSort(): void
    {
        $this->createForm(['form_key' => 'zeta-form']);
        $this->createForm(['form_key' => 'alpha-form']);

        $result = $this->get('/api/v1/cms/forms?projection=list&search=alpha&sort=form_key&per_page=10');

        $result->assertStatus(200);
        $body = json_decode((string) $result->response()->getBody(), true);
        $this->assertSame(1, $body['meta']['total']);
        $this->assertSame('alpha-form', $body['data'][0]['form_key']);
        $this->assertCount(1, $body['data'][0]['translations']);
        $this->assertSame(0, (int) $body['data'][0]['fields_count']);
    }

    public function testDeleteWithSubmissionDeactivatesInsteadOfRemovingForm(): void
    {
        $formId = $this->createForm(['form_key' => 'submitted-form']);

        $this->db->table('cms_form_submissions')->insert([
            'form_key' => 'submitted-form',
            'form_id' => $formId,
            'page_id' => null,
            'language_id' => $this->languages[0]['id'],
            'data_json' => json_encode(['email' => 'visitor@example.test'], JSON_THROW_ON_ERROR),
            'status' => 'new',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $delete = $this->delete("/api/v1/cms/forms/{$formId}");
        $delete->assertStatus(200);

        $row = $this->db->table('cms_forms')->where('id', $formId)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['is_active']);
    }
}
