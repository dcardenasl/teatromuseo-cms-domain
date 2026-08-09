<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\ThrottleFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

final class InspectableThrottleFilter extends ThrottleFilter
{
    /** @return list<array{key: string, limit: int, window: int}> */
    public function buckets(RequestInterface $request): array
    {
        return $this->resolveBuckets($request);
    }
}

final class ThrottleFilterTest extends CIUnitTestCase
{
    public function testPublicReadsUseTheTrustedAppKeyBucket(): void
    {
        $request = $this->makeRequest('/api/v1/cms/public/settings', 'web-key');
        $buckets = (new InspectableThrottleFilter())->buckets($request);

        $this->assertCount(1, $buckets);
        $this->assertSame('rate_limit_public_read_app_' . hash('sha256', 'web-key'), $buckets[0]['key']);
        $this->assertSame(600, $buckets[0]['limit']);
        $this->assertSame(60, $buckets[0]['window']);
    }

    public function testPublicWritesKeepTheInheritedIpBucket(): void
    {
        $request = $this->makeRequest('/api/v1/cms/public/submissions', 'web-key', 'POST');
        $buckets = (new InspectableThrottleFilter())->buckets($request);

        $this->assertCount(1, $buckets);
        $this->assertStringStartsWith('rate_limit_ip_', $buckets[0]['key']);
    }

    private function makeRequest(string $path, string $appKey, string $method = 'GET'): IncomingRequest
    {
        $request = new IncomingRequest(
            new App(),
            new URI('https://example.test' . $path),
            null,
            new UserAgent()
        );
        $request->setMethod($method);
        $request->setHeader('X-App-Key', $appKey);

        return $request;
    }
}
