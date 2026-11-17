<?php

namespace Database\Seeders;

use App\Packs\PackLoader;
use Illuminate\Database\Seeder;

/**
 * Content comes from company packs, not seeders. `php artisan db:seed` installs the
 * fictional demo pack; for your own company use `php artisan pack:install packs/<folder>`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(PackLoader $loader): void
    {
        $summary = $loader->install(base_path('packs/demo'));
        $this->command?->info('Demo pack installed: '.json_encode($summary));
    }
}
