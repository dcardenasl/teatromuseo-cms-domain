<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Validates inbound calls from the Hub (the only caller allowed to reach
 * /api/v1/internal/files/* routes) via an HMAC signature instead of a
 * plain shared key.
 *
 * Why HMAC and not a plain X-App-Key like WebAppKeyRequiredFilter: the Hub's
 * X-App-Key values are stored as a one-way SHA-256 hash (ApiKeyMaterialService),
 * so the Hub can never re-present a domain's own key back to it as proof of
 * identity. hub.internalSecret is a separate, symmetric secret configured
 * identically on the Hub and every domain app specifically for this
 * Hub-initiated (reverse) direction of communication.
 *
 * Signed string: "{METHOD}\n{PATH}\n{TIMESTAMP}" — HMAC-SHA256 with
 * hub.internalSecret, hex-encoded, in X-Hub-Signature. X-Hub-Timestamp must be
 * within 5 minutes of now to bound replay of a captured request.
 */
class HubSignatureFilter implements FilterInterface
{
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ResponseInterface|null
    {
        $secret = (string) config('Hub')->internalSecret;
        if ($secret === '') {
            // Fail closed: an unconfigured gate is a misconfiguration, not
            // "no gate" — same posture as WebAppKeyRequiredFilter.
            return $this->deny(403, 'hub.internalSecret is not configured.');
        }

        $timestamp = $request->getHeaderLine('X-Hub-Timestamp');
        $signature = $request->getHeaderLine('X-Hub-Signature');
        if ($timestamp === '' || $signature === '') {
            return $this->deny(401, 'Missing signature headers.');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            return $this->deny(401, 'Stale or invalid timestamp.');
        }

        $method = strtoupper($request->getMethod());
        $path = self::normalizePath($request->getUri()->getPath());
        $expected = hash_hmac('sha256', $method . "\n" . $path . "\n" . $timestamp, $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->deny(401, 'Invalid signature.');
        }

        return null;
    }

    /**
     * `php spark serve` (CI4's built-in dev server, no rewrite rules) exposes
     * every route under `/index.php/...`; a real webserver with clean-URL
     * rewriting (production, or Apache/Nginx in dev) does not. Strip that
     * front-controller segment so the same signature verifies identically in
     * both environments — the Hub always signs the clean path.
     */
    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php'));
        }

        return $path;
    }

    private function deny(int $status, string $message): ResponseInterface
    {
        return \Config\Services::response()
            ->setStatusCode($status)
            ->setJSON([
                'status'   => 'error',
                'messages' => [$message],
            ]);
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface|null
    {
        return null;
    }
}
