<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Seeds canonical public redirects for legacy public slugs.
 */
final class CmsTeatroMuseoRedirectSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $this->upsertRecord('cms_redirects', [
            'old_path' => 'obras',
        ], [
            'new_url' => config(\Config\Cms::class)->eventListingPath,
            'redirect_type' => 301,
            'is_active' => 1,
            'hit_count' => 0,
            'last_hit_at' => null,
            'note' => 'Legacy works slug now resolves to the event listing.',
        ]);
    }
}
