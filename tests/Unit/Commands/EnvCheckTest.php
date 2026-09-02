<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\EnvCheck;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(EnvCheck::class)]
final class EnvCheckTest extends CIUnitTestCase
{
    private EnvCheck $command;

    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new EnvCheck(service('logger'), service('commands'));
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
        $this->envBackup = [];

        parent::tearDown();
    }

    /**
     * @param  array<string, string> $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $this->envBackup)) {
                $this->envBackup[$key] = $_ENV[$key] ?? false;
            }
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    private function validResolver(): callable
    {
        $values = [
            'app.baseURL'                => 'http://localhost:8090',
            'database.default.hostname'  => '127.0.0.1',
            'database.default.database'  => 'cms_test',
            'database.default.username'  => 'root',
            'encryption.key'             => 'hex2bin:' . bin2hex(random_bytes(32)),
            'hub.url'                    => 'http://localhost:8180',
            'hub.apiKey'                 => 'apk_valid_key',
            'hub.appCode'                => 'cms',
        ];

        return static fn (string $key) => $values[$key] ?? null;
    }

    public function testValidateReturnsNoErrorsForFullyValidEnvironment(): void
    {
        $errors = $this->command->validate($this->validResolver(), 'testing');

        $this->assertSame([], $errors);
    }

    public function testValidateReportsMissingRequiredVariables(): void
    {
        $errors = $this->command->validate(static fn (string $key) => null, 'testing');

        $this->assertContains('app.baseURL is not set', $errors);
        $this->assertContains('database.default.hostname is not set', $errors);
        $this->assertContains('encryption.key is not set', $errors);
        $this->assertContains('hub.url is not set', $errors);
    }

    public function testValidateReportsEmptyRequiredVariables(): void
    {
        $errors = $this->command->validate(static fn (string $key) => '   ', 'testing');

        $this->assertContains('app.baseURL is empty', $errors);
    }

    public function testValidateReportsSecretTooShort(): void
    {
        $values = $this->baseValidValues();
        $values['encryption.key'] = 'hex2bin:' . bin2hex(random_bytes(8)); // 8 bytes, needs 32

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing');

        $this->assertContains('encryption.key is too short', $errors);
    }

    public function testValidateReportsPlaceholderSecret(): void
    {
        $values = $this->baseValidValues();
        // Placeholder detection matches on the raw resolver value (not the
        // hex2bin/base64-decoded form), so this must be an un-prefixed string.
        $values['encryption.key'] = str_pad('change-me-', 40, 'x');

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing');

        $this->assertContains('encryption.key appears to be a placeholder', $errors);
    }

    public function testValidateReportsLowEntropySecretAsPlaceholder(): void
    {
        $values = $this->baseValidValues();
        // 40 repeated 'a' characters — not decodable as hex2bin, treated as raw, low entropy.
        $values['encryption.key'] = str_repeat('a', 40);

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing');

        $this->assertContains('encryption.key appears to be a placeholder', $errors);
    }

    public function testValidateAcceptsBase64EncodedSecret(): void
    {
        $values = $this->baseValidValues();
        $values['encryption.key'] = 'base64:' . base64_encode(random_bytes(32));

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing');

        $this->assertSame([], $errors);
    }

    public function testValidateRequiresCorsOriginsInProduction(): void
    {
        $values = $this->baseValidValues();

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'production');

        $this->assertContains('CORS_ALLOWED_ORIGINS is required in production', $errors);
    }

    public function testValidateRequiresRecommendedVarsInStrictMode(): void
    {
        $values = $this->baseValidValues();

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing', true);

        $this->assertContains('CORS_ALLOWED_ORIGINS is required in production', $errors);
        $this->assertContains('SENTRY_DSN is required in strict mode', $errors);
    }

    public function testValidateIgnoresRecommendedVarsOutsideProductionOrStrict(): void
    {
        $values = $this->baseValidValues();

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing', false);

        $this->assertSame([], $errors);
    }

    public function testValidatePassesWhenRecommendedVarsAreSetInStrictMode(): void
    {
        $values                             = $this->baseValidValues();
        $values['CORS_ALLOWED_ORIGINS']     = 'https://example.com';
        $values['SENTRY_DSN']               = 'https://sentry.example.com/1';

        $errors = $this->command->validate(static fn (string $key) => $values[$key] ?? null, 'testing', true);

        $this->assertSame([], $errors);
    }

    public function testCollectReportsBuildsGreenReportForValidEnvironment(): void
    {
        $this->setEnv([
            'app.baseURL'               => 'http://localhost:8090',
            'database.default.hostname' => '127.0.0.1',
            'database.default.database' => 'cms_test',
            'database.default.username' => 'root',
            'encryption.key'            => 'hex2bin:' . bin2hex(random_bytes(32)),
            'hub.url'                   => 'http://localhost:8180',
            'hub.apiKey'                => 'apk_valid_key',
            'hub.appCode'               => 'cms',
        ]);

        $reports = $this->invokeCollectReports(false);

        $errors = array_merge(...array_column($reports, 'errors'));
        $this->assertSame([], $errors);

        $coreReport = $reports[0];
        $this->assertSame('Core', $coreReport['heading']);
        $this->assertSame("✓ app.baseURL", $coreReport['lines'][0]['text']);
        $this->assertSame('green', $coreReport['lines'][0]['color']);
    }

    public function testCollectReportsFlagsMissingAndShortSecrets(): void
    {
        $this->setEnv([
            'app.baseURL'               => '',
            'database.default.hostname' => '127.0.0.1',
            'database.default.database' => 'cms_test',
            'database.default.username' => 'root',
            'encryption.key'            => 'hex2bin:' . bin2hex(random_bytes(4)),
            'hub.url'                   => 'http://localhost:8180',
            'hub.apiKey'                => 'apk_valid_key',
            'hub.appCode'               => 'cms',
        ]);

        $reports = $this->invokeCollectReports(false);

        $errors = array_merge(...array_column($reports, 'errors'));
        $this->assertContains('app.baseURL is empty', $errors);
        $this->assertContains('encryption.key is too short', $errors);

        $coreReport = $reports[0];
        $this->assertSame('✗ app.baseURL — EMPTY', $coreReport['lines'][0]['text']);
        $this->assertSame('red', $coreReport['lines'][0]['color']);
    }

    public function testCollectReportsAddsStrictModeSectionWhenRecommendedVarsMissing(): void
    {
        $this->setEnv([
            'app.baseURL'               => 'http://localhost:8090',
            'database.default.hostname' => '127.0.0.1',
            'database.default.database' => 'cms_test',
            'database.default.username' => 'root',
            'encryption.key'            => 'hex2bin:' . bin2hex(random_bytes(32)),
            'hub.url'                   => 'http://localhost:8180',
            'hub.apiKey'                => 'apk_valid_key',
            'hub.appCode'               => 'cms',
            // Force the "missing" branch deterministically — a developer's
            // local .env may otherwise already define these.
            'CORS_ALLOWED_ORIGINS'      => '',
            'SENTRY_DSN'                => '',
        ]);

        $reports = $this->invokeCollectReports(true);

        $headings = array_column($reports, 'heading');
        $this->assertContains('Strict mode', $headings);

        $strictReport = $reports[array_search('Strict mode', $headings, true)];
        $this->assertContains('CORS_ALLOWED_ORIGINS is required in production', $strictReport['errors']);
        $this->assertContains('SENTRY_DSN is required in strict mode', $strictReport['errors']);
    }

    public function testRunPrintsSuccessAndReturnsWithoutExitingWhenEnvironmentIsValid(): void
    {
        $this->setEnv([
            'app.baseURL'               => 'http://localhost:8090',
            'database.default.hostname' => '127.0.0.1',
            'database.default.database' => 'cms_test',
            'database.default.username' => 'root',
            'encryption.key'            => 'hex2bin:' . bin2hex(random_bytes(32)),
            'hub.url'                   => 'http://localhost:8180',
            'hub.apiKey'                => 'apk_valid_key',
            'hub.appCode'               => 'cms',
        ]);

        // If run() reached its internal exit(1) branch this would terminate the
        // whole test process, so this assertion only holds true when the
        // "all checks passed" return path was taken.
        $this->command->run([]);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, string>
     */
    private function baseValidValues(): array
    {
        return [
            'app.baseURL'                => 'http://localhost:8090',
            'database.default.hostname'  => '127.0.0.1',
            'database.default.database'  => 'cms_test',
            'database.default.username'  => 'root',
            'encryption.key'             => 'hex2bin:' . bin2hex(random_bytes(32)),
            'hub.url'                    => 'http://localhost:8180',
            'hub.apiKey'                 => 'apk_valid_key',
            'hub.appCode'                => 'cms',
        ];
    }

    /**
     * @return list<array{heading: string|null, lines: list<array{text: string, color: string}>, errors: list<string>}>
     */
    private function invokeCollectReports(bool $strict): array
    {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('collectReports');
        $method->setAccessible(true);

        return $method->invoke($this->command, $strict);
    }
}
