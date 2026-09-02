<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\Doctor;
use App\Libraries\Hub\HubClient;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Doctor::class)]
final class DoctorTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::resetSingle('hubClient');
    }

    protected function tearDown(): void
    {
        Services::resetSingle('hubClient');
        parent::tearDown();
    }

    public function testReportsAllChecksWhenTokensAreProvided(): void
    {
        $stub = new class () extends HubClient {
            public int $serviceTokenCalls = 0;
            public int $introspectCalls = 0;
            public int $registerPermissionCalls = 0;

            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                $this->serviceTokenCalls++;

                return 'service-token-abc';
            }

            public function introspect(string $token): IntrospectResult
            {
                $this->introspectCalls++;

                return new IntrospectResult(
                    valid: true,
                    uid: 42,
                    permissions: ['items.read', 'items.write'],
                    exp: time() + 3600,
                    error: null,
                );
            }

            public function registerPermission(array $permission, string $bearerToken, ?int $applicationId = null): bool
            {
                $this->registerPermissionCalls++;

                return false;
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose(['--token=jwt-token', '--admin-token=admin-token']);

        $this->assertFalse($report['hasErrors']);
        $this->assertSame('ok', $report['checks'][0]['status']);
        $this->assertSame('service-token', $report['checks'][0]['label']);
        $this->assertSame('acquired (17 chars)', $report['checks'][0]['detail']);
        $this->assertSame('ok', $report['checks'][1]['status']);
        $this->assertSame('introspect', $report['checks'][1]['label']);
        $this->assertSame('valid for user 42 (2 permissions)', $report['checks'][1]['detail']);
        $this->assertSame('ok', $report['checks'][2]['status']);
        $this->assertSame('register-permission', $report['checks'][2]['label']);
        $this->assertSame('hub returned a structured response', $report['checks'][2]['detail']);
        $this->assertSame(1, $stub->serviceTokenCalls);
        $this->assertSame(1, $stub->introspectCalls);
        $this->assertSame(1, $stub->registerPermissionCalls);
    }

    public function testSkipsOptionalChecksWithoutTokens(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return 'service-token';
            }

            public function introspect(string $token): IntrospectResult
            {
                throw new \RuntimeException('Introspect should not be called when no token is provided.');
            }

            public function registerPermission(array $permission, string $bearerToken, ?int $applicationId = null): bool
            {
                throw new \RuntimeException('registerPermission should not be called when no admin token is provided.');
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose([]);

        $this->assertFalse($report['hasErrors']);
        $this->assertSame('ok', $report['checks'][0]['status']);
        $this->assertSame('skipped', $report['checks'][1]['status']);
        $this->assertSame('skipped', $report['checks'][2]['status']);
    }

    public function testReportsFailureWhenServiceTokenIsEmpty(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return '';
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose([]);

        $this->assertTrue($report['hasErrors']);
        $this->assertSame('fail', $report['checks'][0]['status']);
        $this->assertSame('hub returned an empty service token', $report['checks'][0]['detail']);
    }

    public function testReportsFailureWhenServiceTokenThrows(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                throw new \RuntimeException('hub unreachable');
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose([]);

        $this->assertTrue($report['hasErrors']);
        $this->assertSame('fail', $report['checks'][0]['status']);
        $this->assertSame('hub unreachable', $report['checks'][0]['detail']);
    }

    public function testReportsFailureWhenIntrospectRejectsToken(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return 'service-token';
            }

            public function introspect(string $token): IntrospectResult
            {
                return new IntrospectResult(valid: false, uid: null, permissions: [], exp: null, error: 'invalid');
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose(['--token=bad-token']);

        $this->assertTrue($report['hasErrors']);
        $this->assertSame('fail', $report['checks'][1]['status']);
        $this->assertSame('token was rejected by the hub', $report['checks'][1]['detail']);
    }

    public function testReportsFailureWhenIntrospectThrows(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return 'service-token';
            }

            public function introspect(string $token): IntrospectResult
            {
                throw new \RuntimeException('introspect timed out');
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose(['--token=jwt-token']);

        $this->assertTrue($report['hasErrors']);
        $this->assertSame('fail', $report['checks'][1]['status']);
        $this->assertSame('introspect timed out', $report['checks'][1]['detail']);
    }

    public function testReportsFailureWhenRegisterPermissionThrows(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return 'service-token';
            }

            public function registerPermission(array $permission, string $bearerToken, ?int $applicationId = null): bool
            {
                throw new \RuntimeException('forbidden');
            }
        };

        Services::injectMock('hubClient', $stub);

        $report = $this->diagnose(['--admin-token=admin-token']);

        $this->assertTrue($report['hasErrors']);
        $this->assertSame('fail', $report['checks'][2]['status']);
        $this->assertSame('forbidden', $report['checks'][2]['detail']);
    }

    public function testRunPrintsSuccessAndReturnsExitSuccessWhenAllChecksPass(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return 'service-token';
            }
        };

        Services::injectMock('hubClient', $stub);

        $command = new Doctor(service('logger'), service('commands'));
        $result = $command->run([]);

        $this->assertSame(EXIT_SUCCESS, $result);
    }

    public function testRunReturnsExitErrorWhenACheckFails(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function getServiceToken(): string
            {
                return '';
            }
        };

        Services::injectMock('hubClient', $stub);

        $command = new Doctor(service('logger'), service('commands'));
        $result = $command->run([]);

        $this->assertSame(EXIT_ERROR, $result);
    }

    /**
     * @param  list<string>  $params
     * @return array{checks: list<array{label: string, status: string, detail: string}>, hasErrors: bool}
     */
    private function diagnose(array $params): array
    {
        $command = new Doctor(service('logger'), service('commands'));

        $token = '';
        $adminToken = '';
        foreach ($params as $param) {
            if (str_starts_with($param, '--token=')) {
                $token = substr($param, strlen('--token='));
            }
            if (str_starts_with($param, '--admin-token=')) {
                $adminToken = substr($param, strlen('--admin-token='));
            }
        }

        return $command->diagnose($token, $adminToken);
    }
}
