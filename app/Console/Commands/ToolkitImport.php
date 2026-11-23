<?php

namespace App\Console\Commands;

use App\Models\ToolkitItem;
use App\Packs\ToolkitImporter;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Load many toolkit items at once from a folder of documents and a toolkit.yaml describing them,
 * without reinstalling the whole company pack. Items are matched on their code and updated.
 */
class ToolkitImport extends Command
{
    protected $signature = 'toolkit:import
        {path? : Pack folder (toolkit.yaml + documents in toolkit/), any folder holding toolkit.yaml and the documents, or the toolkit.yaml itself. Default: packs/<installed pack>}
        {--validate-only : Show what would be imported without changing anything}';

    protected $description = 'Import ISMS toolkit items (procedures, forms, guides) and their template files';

    public function handle(ToolkitImporter $importer): int
    {
        $path = rtrim((string) ($this->argument('path') ?? 'packs/'.setting('pack.slug', 'company')), '/\\');
        if (! file_exists($path) && file_exists(base_path($path))) {
            $path = base_path($path);
        }
        $yaml = is_dir($path) ? $path.DIRECTORY_SEPARATOR.'toolkit.yaml' : $path;
        if (is_dir($path) && ! is_file($yaml) && is_file($path.'/toolkit/toolkit.yaml')) {
            $yaml = $path.'/toolkit/toolkit.yaml';
        }
        if (! is_file($yaml)) {
            $this->error("toolkit.yaml was not found: $yaml");
            $this->line('Pass the folder that holds it, e.g. php artisan toolkit:import packs/my-company');

            return self::FAILURE;
        }
        $dir = dirname($yaml);
        // In a pack the documents live in toolkit/ (e.g. toolkit/Δ, toolkit/Ε); otherwise next to toolkit.yaml.
        $searchDir = is_dir($dir.'/toolkit') ? $dir.'/toolkit' : $dir;
        $this->line('Reading '.$yaml.'; looking for documents in '.$searchDir);

        try {
            $items = Yaml::parseFile($yaml) ?? [];
        } catch (ParseException $e) {
            $this->error('toolkit.yaml: '.$e->getMessage());

            return self::FAILURE;
        }
        if (! is_array($items)) {
            $this->error('toolkit.yaml: must be a list of items.');

            return self::FAILURE;
        }

        [$items, $errors, $warnings] = array_is_list($items) ? $importer->findFiles($items, $dir, $searchDir) : [$items, [], []];
        $errors = [...$errors, ...$importer->validate($items, $dir, ToolkitItem::pluck('code')->all(), 'in the folder')];

        if (array_is_list($items)) {
            $this->table(['Code', 'Type', 'Template file'], collect($items)->filter(fn ($t) => is_array($t))->map(fn ($t) => [
                $t['code'] ?? '?', $t['type'] ?? '?', $t['file'] ?? '—',
            ])->all());
        }
        foreach ($warnings as $warning) {
            $this->warn('  ! '.$warning);
        }
        if ($errors) {
            $this->error('Nothing was imported. Please fix:');
            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }
        if ($this->option('validate-only')) {
            $this->info(count($items).' items are ready to import. Run again without --validate-only to import them.');

            return self::SUCCESS;
        }

        $count = DB::transaction(fn () => $importer->install($items, $dir));
        Audit::log('toolkit.imported', null, [
            'items' => $count, 'files' => count(array_filter(array_column($items, 'file'))),
            'codes' => array_column($items, 'code'),
        ]);
        $this->info("$count toolkit items imported.");

        return self::SUCCESS;
    }
}
