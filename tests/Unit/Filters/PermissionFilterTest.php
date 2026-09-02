<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\PermissionFilter;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PermissionFilter::class)]
final class PermissionFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ContextHolder::flush();
    }

    protected function tearDown(): void
    {
        ContextHolder::flush();
        parent::tearDown();
    }

    public function testReturns401WhenNoActorIsAuthenticated(): void
    {
        $response = (new PermissionFilter())->before($this->makeRequest(), ['cms.pages.read']);

        $this->assertSame(401, $this->statusCode($response));
    }

    public function testAllowsAUserWhoHasTheRequiredPermission(): void
    {
        $result = (new PermissionFilter())->before(
            $this->makeRequest(42, ['cms.pages.read']),
            ['cms.pages.read'],
        );

        $this->assertNull($result);
    }

    public function testReturns403WhenAUserLacksTheRequiredPermission(): void
    {
        $response = (new PermissionFilter())->before(
            $this->makeRequest(42, ['cms.pages.write']),
            ['cms.pages.read'],
        );

        $this->assertSame(403, $this->statusCode($response));
    }

    public function testAllowsSuperadminWithoutTheDomainPermission(): void
    {
        $result = (new PermissionFilter())->before(
            $this->makeRequest(42, ['iam.superadmin-access']),
            ['cms.pages.read'],
        );

        $this->assertNull($result);
    }

    public function testStillRejectsAnEmptyPermissionRequirement(): void
    {
        $response = (new PermissionFilter())->before(
            $this->makeRequest(42, ['iam.superadmin-access']),
            [''],
        );

        $this->assertSame(403, $this->statusCode($response));
    }

    public function testFallsBackToPermissionsFromTheContextHolder(): void
    {
        ContextHolder::set(new SecurityContext(user_id: 42, permissions: ['cms.pages.read']));

        $result = (new PermissionFilter())->before(
            $this->makeRequest(42),
            ['cms.pages.read'],
        );

        $this->assertNull($result);
    }

    /**
     * @param list<string> $permissions
     */
    private function makeRequest(?int $userId = null, array $permissions = []): ApiRequest
    {
        $request = new ApiRequest(
            new App(),
            \Config\Services::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent(),
        );
        $request->setAuthContext($userId, $permissions);

        return $request;
    }

    private function statusCode(mixed $response): int
    {
        $this->assertInstanceOf(ResponseInterface::class, $response);

        return $response instanceof ResponseInterface ? (int) $response->getStatusCode() : 0;
    }
}
