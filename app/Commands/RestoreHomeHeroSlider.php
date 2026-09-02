<?php

declare(strict_types=1);

namespace App\Commands;

use App\Database\Seeds\LegacyHomeHeroSliderSeeder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class RestoreHomeHeroSlider extends BaseCommand
{
    protected $group = 'CMS';
    protected $name = 'cms:restore-home-slider';
    protected $description = 'Restore the five migrated home hero slides without overwriting editorial content.';

    public function run(array $params): void
    {
        CLI::write('Restoring migrated homepage hero slides...', 'yellow');
        config('Database');
        (new LegacyHomeHeroSliderSeeder(config('Database')))->run();
    }
}
