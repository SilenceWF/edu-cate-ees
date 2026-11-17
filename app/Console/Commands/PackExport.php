<?php

namespace App\Console\Commands;

use App\Packs\PackExporter;
use Illuminate\Console\Command;

class PackExport extends Command
{
    protected $signature = 'pack:export';

    protected $description = 'Export the current content as a company pack (.zip)';

    public function handle(PackExporter $exporter): int
    {
        $this->info('Pack written to '.$exporter->export());

        return self::SUCCESS;
    }
}
