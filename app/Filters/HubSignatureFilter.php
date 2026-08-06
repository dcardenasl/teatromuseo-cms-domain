<?php

declare(strict_types=1);

namespace App\Filters;

use dcardenasl\Ci4ApiCore\Http\Filters\AbstractHubSignatureFilter;

/**
 * Validates inbound calls from the Hub (the only caller allowed to reach
 * /api/v1/internal/files/* routes) via an HMAC signature instead of a
 * plain shared key.
 *
 * Why HMAC and not a plain X-App-Key like WebAppKeyRequiredFilter: the Hub's
 * X-App-Key values are stored as a one-way SHA-256 hash (ApiKeyMaterialService),
 * so the Hub can never re-present a domain's own key back to it as proof of
 * identity. `hub.internalSecret` is a separate, symmetric secret configured
 * identically on the Hub and every domain app specifically for this
 * Hub-initiated (reverse) direction of communication.
 */
class HubSignatureFilter extends AbstractHubSignatureFilter
{
    protected function hubSecret(): string
    {
        return (string) config('Hub')->internalSecret;
    }
}
