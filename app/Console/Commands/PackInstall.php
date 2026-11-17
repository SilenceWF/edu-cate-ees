<?php

namespace App\Console\Commands;

use App\Packs\PackException;
use App\Packs\PackLoader;
use Illuminate\Console\Command;

class PackInstall extends Command
{
    protected $signature = 'pack:install {path : Pack folder or .zip} {--validate-only : Check the pack without installing it}';

    protected $aliases = ['pack:validate'];

    protected $description = 'Install (or just validate) a company pack';

    public function handle(PackLoader $loader): int
    {
        try {
            if ($this->option('validate-only')) {
                $loader->read($this->argument('path'));
                $this->info('Pack is valid.');

                return self::SUCCESS;
            }
            $summary = $loader->install($this->argument('path'));
        } catch (PackException $e) {
            $this->error('The pack has problems:');
            foreach ($e->errors as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }

        $this->info('Pack installed.');
        $this->table(['Item', 'Count'], collect($summary)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }
}
