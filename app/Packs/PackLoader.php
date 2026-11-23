<?php

namespace App\Packs;

use App\Models\Campaign;
use App\Models\Department;
use App\Models\Policy;
use App\Models\QuizQuestion;
use App\Models\ToolkitItem;
use App\Models\Track;
use App\Models\TrainingGroup;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\Audit;
use App\Services\RosterImporter;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * Reads a company pack (a folder or a .zip of one), validates it and loads it into the
 * database. Installing is idempotent: records are matched on stable keys and updated,
 * and nothing that holds training evidence is ever deleted.
 *
 * See docs/PACK_GUIDE.md for the format.
 */
class PackLoader
{
    private array $errors = [];

    public function __construct(private Settings $settings, private RosterImporter $roster) {}

    /** Resolve a folder or zip to a pack folder (the one containing pack.yaml). */
    public function resolve(string $path): string
    {
        if (is_file($path)) {
            $target = storage_path('app/pack-uploads/'.Str::random(12));
            File::ensureDirectoryExists($target);
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new PackException([__('pack.errors.bad_zip')]);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    throw new PackException([__('pack.errors.bad_zip')]);
                }
            }
            $zip->extractTo($target);
            $zip->close();
            $path = $target;
        }

        if (! is_dir($path)) {
            throw new PackException([__('pack.errors.not_found', ['path' => $path])]);
        }
        if (is_file($path.'/pack.yaml')) {
            return $path;
        }
        // Zips usually wrap the pack in one top-level folder.
        foreach (File::directories($path) as $dir) {
            if (is_file($dir.'/pack.yaml')) {
                return $dir;
            }
        }

        throw new PackException([__('pack.errors.no_manifest')]);
    }

    /** Parse and validate. Returns the parsed pack; throws PackException listing every problem. */
    public function read(string $path): array
    {
        $dir = $this->resolve($path);
        $this->errors = [];

        $pack = [
            'dir' => $dir,
            'manifest' => $this->yaml($dir, 'pack.yaml') ?? [],
            'tracks' => $this->yaml($dir, 'tracks.yaml') ?? [],
            'groups' => $this->yaml($dir, 'groups.yaml') ?? [],
            'departments' => $this->yaml($dir, 'departments.yaml', optional: true) ?? [],
            'policies' => $this->collect($dir, 'policies'),
            'modules' => $this->collect($dir, 'modules'),
            'quizzes' => [],
            'people' => is_file($dir.'/people.csv') ? $this->roster->readCsv($dir.'/people.csv') : [],
            'toolkit' => $this->yaml($dir, 'toolkit.yaml', optional: true) ?? [],
        ];
        foreach (glob($dir.'/quizzes/*.{yaml,yml}', GLOB_BRACE) ?: [] as $file) {
            $pack['quizzes'][pathinfo($file, PATHINFO_FILENAME)] = $this->yaml($dir, 'quizzes/'.basename($file)) ?? [];
        }

        $this->validate($pack);

        if ($this->errors) {
            throw new PackException($this->errors);
        }

        return $pack;
    }

    /** Install a pack. Returns counts of what was loaded. */
    public function install(string $path): array
    {
        $pack = $this->read($path);
        $m = $pack['manifest'];

        $summary = DB::transaction(function () use ($pack, $m) {
            $this->storeSettings($pack);

            $moduleIds = [];
            foreach (array_values($pack['modules']) as $i => $mod) {
                $moduleIds[$mod['key']] = TrainingModule::updateOrCreate(['key' => $mod['key']], [
                    'title' => $this->tr($mod['title']),
                    'subtitle' => isset($mod['subtitle']) ? $this->tr($mod['subtitle']) : null,
                    'estimated_minutes' => (int) ($mod['minutes'] ?? 5),
                    'slides' => $mod['slides'],
                    'sort_order' => $mod['sort_order'] ?? $i + 1,
                ])->id;
            }

            $trackIds = [];
            foreach (array_values($pack['tracks']) as $i => $t) {
                $track = Track::updateOrCreate(['key' => $t['key']], [
                    'name' => $this->tr($t['name']),
                    'quiz_question_count' => (int) ($t['quiz_questions'] ?? 8),
                    'quiz_from' => $t['quiz_from'] ?? null,
                    'requires_policies' => (bool) ($t['policies'] ?? true),
                    'kiosk_enabled' => (bool) ($t['kiosk'] ?? false),
                    'sort_order' => $i + 1,
                ]);
                $track->modules()->sync(collect($t['modules'] ?? [])->values()
                    ->mapWithKeys(fn ($key, $n) => [$moduleIds[$key] => ['sort_order' => $n + 1]])->all());
                $trackIds[$t['key']] = $track->id;
            }

            foreach (array_values($pack['groups']) as $i => $g) {
                TrainingGroup::updateOrCreate(['key' => $g['key']], [
                    'name' => $this->tr($g['name']),
                    'track_id' => $trackIds[$g['track']],
                    'description' => isset($g['description']) ? $this->tr($g['description']) : null,
                    'sort_order' => $i + 1,
                ]);
            }

            foreach ($pack['departments'] as $d) {
                Department::updateOrCreate(['name' => $d['name']], [
                    'location' => $d['location'] ?? null,
                    'hex_color' => strtoupper($d['color'] ?? '#64748B'),
                ]);
            }

            foreach (array_values($pack['policies']) as $i => $p) {
                Policy::updateOrCreate(['code' => $p['code']], [
                    'title' => $this->tr($p['title']),
                    'version' => (string) ($p['version'] ?? '1'),
                    'effective_date' => $p['effective_date'] ?? null,
                    'document_url' => $this->storeDocument($pack['dir'], $p['document'] ?? null),
                    'key_rules' => $p['key_rules'] ?? [],
                    'is_mandatory_all' => (bool) ($p['mandatory_all'] ?? true),
                    'track_keys' => $p['tracks'] ?? null,
                    'sort_order' => $p['sort_order'] ?? $i + 1,
                ]);
            }

            $questions = 0;
            foreach ($pack['quizzes'] as $trackKey => $list) {
                foreach (array_values($list) as $i => $q) {
                    QuizQuestion::updateOrCreate(['pack_key' => $trackKey.':'.($q['key'] ?? $i + 1)], [
                        'track_id' => $trackIds[$trackKey],
                        'question_text' => $this->tr($q['question']),
                        'options' => $q['options'],
                        'correct_option' => strtoupper((string) $q['correct']),
                        'explanation' => isset($q['explanation']) ? $this->tr($q['explanation']) : null,
                        'sort_order' => $i + 1,
                    ]);
                    $questions++;
                }
            }

            foreach (array_values($pack['toolkit']) as $i => $t) {
                $file = $this->storeToolkitFile($pack['dir'], $t['file'] ?? null);
                $item = ToolkitItem::firstOrNew(['code' => (string) $t['code']]);
                $item->fill([
                    'type' => $t['type'],
                    'title' => $this->tr($t['title']),
                    'summary' => isset($t['summary']) ? $this->tr($t['summary']) : null,
                    'when_to_use' => isset($t['when_to_use']) ? $this->tr($t['when_to_use']) : null,
                    'roles_guidance' => isset($t['roles_guidance']) ? $this->tr($t['roles_guidance']) : null,
                    'example_usage' => isset($t['example_usage']) ? $this->tr($t['example_usage']) : null,
                    'related_codes' => array_values(array_map('strval', (array) ($t['related'] ?? []))) ?: null,
                    'file_path' => $file,
                    'original_filename' => $file ? basename((string) $t['file']) : null,
                    'sort_order' => $t['sort_order'] ?? $i + 1,
                ]);
                // Like the PIN and pass mark, an item an admin switched off stays off unless the pack says otherwise.
                if (array_key_exists('active', $t) || ! $item->exists) {
                    $item->is_active = (bool) ($t['active'] ?? true);
                }
                $item->save();
            }

            if (! Campaign::where('is_current', true)->exists()) {
                $c = $m['campaign'] ?? [];
                Campaign::create([
                    'name' => $c['name'] ?? (string) now()->year,
                    'starts_on' => $c['starts_on'] ?? now()->toDateString(),
                    'ends_on' => $c['ends_on'] ?? null,
                    'is_current' => true,
                ]);
            }

            $people = $pack['people'] ? $this->roster->apply($pack['people'])['summary'] : ['create' => 0, 'update' => 0];

            return [
                'tracks' => count($pack['tracks']), 'groups' => count($pack['groups']),
                'departments' => count($pack['departments']), 'policies' => count($pack['policies']),
                'modules' => count($pack['modules']), 'questions' => $questions,
                'people_created' => $people['create'], 'people_updated' => $people['update'],
                'toolkit' => count($pack['toolkit']),
            ];
        });

        Audit::log('pack.installed', null, ['slug' => $m['slug'], 'version' => $m['version'] ?? null] + $summary);

        return $summary;
    }

    private function storeSettings(array $pack): void
    {
        $m = $pack['manifest'];
        $dir = $pack['dir'];

        // A logo uploaded in Admin → Settings wins over the pack's, like the PIN and pass mark.
        $current = (array) $this->settings->get('branding', []);
        $keepAdminLogo = ($current['logo_source'] ?? null) === 'admin';
        $logo = $keepAdminLogo ? ($current['logo'] ?? null) : null;
        if (! $keepAdminLogo && ! empty($m['branding']['logo']) && is_file($dir.'/'.$m['branding']['logo'])) {
            $logo = 'logo.'.strtolower(pathinfo($m['branding']['logo'], PATHINFO_EXTENSION));
            File::ensureDirectoryExists(storage_path('app/pack/branding'));
            File::copy($dir.'/'.$m['branding']['logo'], storage_path('app/pack/branding/'.$logo));
        }

        $this->settings->set('pack', ['slug' => $m['slug'], 'version' => $m['version'] ?? '1', 'installed_at' => now()->toIso8601String()]);
        $this->settings->set('company', $m['company']);
        $this->settings->set('locales', array_values($m['locales'] ?? ['el', 'en']));
        $this->settings->set('default_locale', $m['default_locale'] ?? ($m['locales'][0] ?? 'el'));
        $this->settings->set('timezone', $m['timezone'] ?? 'Europe/Athens');
        $keepAdminFavicon = ($current['favicon_source'] ?? null) === 'admin';
        $favicon = $keepAdminFavicon ? ($current['favicon'] ?? null) : null;
        if (! $keepAdminFavicon && ! empty($m['branding']['favicon']) && is_file($dir.'/'.$m['branding']['favicon'])) {
            $favicon = 'favicon.'.strtolower(pathinfo($m['branding']['favicon'], PATHINFO_EXTENSION));
            File::ensureDirectoryExists(storage_path('app/pack/branding'));
            File::copy($dir.'/'.$m['branding']['favicon'], storage_path('app/pack/branding/'.$favicon));
        }

        $this->settings->set('branding', array_filter([
            'logo' => $logo, 'logo_source' => $keepAdminLogo ? 'admin' : null,
            'favicon' => $favicon, 'favicon_source' => $keepAdminFavicon ? 'admin' : null,
            'colors' => $m['branding']['colors'] ?? [],
        ], fn ($v) => $v !== null));
        $this->settings->set('declaration', $m['declaration'] ?? null);
        $this->settings->set('import', $m['import'] ?? []);
        $this->settings->set('locations', $m['locations'] ?? []);

        // Operational settings an admin may have changed are only seeded, never overwritten.
        if ($this->settings->get('quiz') === null) {
            $this->settings->set('quiz', ['pass_threshold' => (int) ($m['quiz']['pass_threshold'] ?? 80)]);
        }
        if ($this->settings->get('kiosk') === null) {
            $this->settings->set('kiosk', ['pin_hash' => Hash::make((string) ($m['kiosk']['pin'] ?? '0000'))]);
        }
    }

    /** Copy a policy PDF shipped inside the pack into private storage; pass URLs through. */
    private function storeDocument(string $dir, ?string $document): ?string
    {
        if (! $document || preg_match('#^https?://#i', $document)) {
            return $document;
        }
        $source = $dir.'/'.ltrim($document, '/');
        if (! is_file($source)) {
            return null;
        }
        File::ensureDirectoryExists(storage_path('app/pack/documents'));
        File::copy($source, storage_path('app/pack/documents/'.basename($source)));

        return basename($source);
    }

    /** Copy a toolkit template shipped inside the pack (toolkit/…) into private storage. */
    private function storeToolkitFile(string $dir, ?string $file): ?string
    {
        if (blank($file)) {
            return null;
        }
        $source = $dir.'/'.ltrim($file, '/');
        if (! is_file($source)) {
            return null;
        }
        File::ensureDirectoryExists(ToolkitItem::storageDir());
        File::copy($source, ToolkitItem::storageDir().'/'.basename($source));

        return basename($source);
    }

    private function validate(array $pack): void
    {
        $m = $pack['manifest'];
        foreach (['slug', 'company.name', 'locales'] as $key) {
            if (blank(data_get($m, $key))) {
                $this->errors[] = "pack.yaml: '$key' is required.";
            }
        }
        foreach ((array) data_get($m, 'branding.colors', []) as $name => $hex) {
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex)) {
                $this->errors[] = "pack.yaml: branding.colors.$name must be a 6-digit hex colour like #F59E0A.";
            }
        }
        if (isset($m['kiosk']['pin']) && ! preg_match('/^\d{4,8}$/', (string) $m['kiosk']['pin'])) {
            $this->errors[] = 'pack.yaml: kiosk.pin must be 4 to 8 digits (quote it: "0420").';
        }

        $moduleKeys = [];
        foreach ($pack['modules'] as $file => $mod) {
            if (blank($mod['key'] ?? null) || blank($mod['title'] ?? null)) {
                $this->errors[] = "$file: 'key' and 'title' are required.";

                continue;
            }
            $moduleKeys[] = $mod['key'];
            if (empty($mod['slides']) || ! is_array($mod['slides'])) {
                $this->errors[] = "$file: at least one slide is required.";
            }
            foreach ((array) ($mod['slides'] ?? []) as $n => $slide) {
                if (blank($slide['title'] ?? null)) {
                    $this->errors[] = "$file: slide ".($n + 1)." needs a 'title'.";
                }
            }
        }

        $trackKeys = [];
        if (! $pack['tracks']) {
            $this->errors[] = 'tracks.yaml: at least one track is required.';
        }
        foreach ($pack['tracks'] as $n => $t) {
            if (blank($t['key'] ?? null) || blank($t['name'] ?? null)) {
                $this->errors[] = 'tracks.yaml: entry '.($n + 1)." needs 'key' and 'name'.";

                continue;
            }
            $trackKeys[] = $t['key'];
            foreach ((array) ($t['modules'] ?? []) as $key) {
                if (! in_array($key, $moduleKeys, true)) {
                    $this->errors[] = "tracks.yaml: track '{$t['key']}' refers to unknown module '$key'.";
                }
            }
            if (isset($t['quiz_from']) && ! in_array($t['quiz_from'], array_column($pack['tracks'], 'key'), true)) {
                $this->errors[] = "tracks.yaml: track '{$t['key']}' has unknown quiz_from '{$t['quiz_from']}'.";
            }
        }

        if (! $pack['groups']) {
            $this->errors[] = 'groups.yaml: at least one group is required.';
        }
        foreach ($pack['groups'] as $n => $g) {
            if (blank($g['key'] ?? null) || blank($g['name'] ?? null)) {
                $this->errors[] = 'groups.yaml: entry '.($n + 1)." needs 'key' and 'name'.";
            }
            if (! in_array($g['track'] ?? null, $trackKeys, true)) {
                $this->errors[] = 'groups.yaml: entry '.($n + 1)." has unknown track '".($g['track'] ?? '')."'.";
            }
        }

        foreach ($pack['departments'] as $n => $d) {
            if (blank($d['name'] ?? null)) {
                $this->errors[] = 'departments.yaml: entry '.($n + 1)." needs a 'name'.";
            }
            if (isset($d['color']) && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $d['color'])) {
                $this->errors[] = 'departments.yaml: entry '.($n + 1).' colour must look like #F59E0A.';
            }
        }

        foreach ($pack['policies'] as $file => $p) {
            if (blank($p['code'] ?? null) || blank($p['title'] ?? null)) {
                $this->errors[] = "$file: 'code' and 'title' are required.";
            }
            foreach ((array) ($p['tracks'] ?? []) as $key) {
                if (! in_array($key, $trackKeys, true)) {
                    $this->errors[] = "$file: unknown track '$key'.";
                }
            }
        }

        foreach ($pack['quizzes'] as $trackKey => $list) {
            $file = "quizzes/$trackKey.yaml";
            if (! in_array($trackKey, $trackKeys, true)) {
                $this->errors[] = "$file: file name must match a track key.";
            }
            foreach ((array) $list as $n => $q) {
                $label = "$file: question ".($n + 1);
                if (blank($q['question'] ?? null)) {
                    $this->errors[] = "$label needs a 'question'.";
                }
                $options = (array) ($q['options'] ?? []);
                if (count($options) < 2) {
                    $this->errors[] = "$label needs at least two options.";
                }
                if (! array_key_exists(strtoupper((string) ($q['correct'] ?? '')), $options)) {
                    $this->errors[] = "$label: 'correct' must be one of the option letters.";
                }
            }
        }
        foreach ($pack['tracks'] as $t) {
            $have = count($pack['quizzes'][$t['quiz_from'] ?? ($t['key'] ?? '')] ?? []);
            if (isset($t['key']) && $have < (int) ($t['quiz_questions'] ?? 8)) {
                $this->errors[] = "quizzes/{$t['key']}.yaml: track asks for ".($t['quiz_questions'] ?? 8)." questions but only $have are defined.";
            }
        }

        if (! array_is_list($pack['toolkit'])) {
            $this->errors[] = 'toolkit.yaml: must be a list of items (each starting with "- code: …").';
        } else {
            $codes = array_map(fn ($t) => is_array($t) ? (string) ($t['code'] ?? '') : '', $pack['toolkit']);
            foreach ($pack['toolkit'] as $n => $t) {
                $label = 'toolkit.yaml: item '.($n + 1);
                if (! is_array($t) || blank($t['code'] ?? null) || blank($t['title'] ?? null)) {
                    $this->errors[] = "$label needs 'code' and 'title'.";

                    continue;
                }
                $label = "toolkit.yaml: '{$t['code']}'";
                if (count(array_keys($codes, (string) $t['code'], true)) > 1) {
                    $this->errors[] = "$label is listed more than once.";
                }
                if (! in_array($t['type'] ?? null, ToolkitItem::TYPES, true)) {
                    $this->errors[] = "$label: 'type' must be one of ".implode(', ', ToolkitItem::TYPES).'.';
                }
                if (filled($t['file'] ?? null)) {
                    $ext = strtolower(pathinfo((string) $t['file'], PATHINFO_EXTENSION));
                    if (! in_array($ext, ToolkitItem::TEMPLATE_EXTENSIONS, true)) {
                        $this->errors[] = "$label: file must be ".implode(', ', ToolkitItem::TEMPLATE_EXTENSIONS).'.';
                    } elseif (str_contains((string) $t['file'], '..') || ! is_file($pack['dir'].'/'.ltrim((string) $t['file'], '/'))) {
                        $this->errors[] = "$label: file '{$t['file']}' was not found in the pack.";
                    }
                }
                foreach ((array) ($t['related'] ?? []) as $code) {
                    if (! in_array((string) $code, $codes, true)) {
                        $this->errors[] = "$label: related code '$code' is not in toolkit.yaml.";
                    }
                }
            }
        }

        $groupKeys = array_column($pack['groups'], 'key');
        foreach ($pack['people'] as $person) {
            if ($person['name'] === '') {
                $this->errors[] = "people.csv line {$person['line']}: 'name' is required.";
            }
            if ($person['group'] !== '' && ! in_array($person['group'], $groupKeys, true)) {
                $this->errors[] = "people.csv line {$person['line']}: unknown group '{$person['group']}' (use a key from groups.yaml).";
            }
            if ($person['role'] && ! in_array($person['role'], User::ROLES, true)) {
                $this->errors[] = "people.csv line {$person['line']}: unknown role '{$person['role']}'.";
            }
        }
    }

    private function yaml(string $dir, string $file, bool $optional = false): ?array
    {
        $path = $dir.'/'.$file;
        if (! is_file($path)) {
            if (! $optional) {
                $this->errors[] = "$file: file is missing.";
            }

            return null;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            $this->errors[] = "$file: ".$e->getMessage();

            return null;
        }

        return is_array($data) ? $data : [];
    }

    /** Read every YAML file in a sub-folder; a file may hold one record or a list of records. */
    private function collect(string $dir, string $folder): array
    {
        $out = [];
        $files = glob("$dir/$folder/*.{yaml,yml}", GLOB_BRACE) ?: [];
        sort($files, SORT_NATURAL);
        foreach ($files as $file) {
            $name = $folder.'/'.basename($file);
            $data = $this->yaml($dir, $name);
            if ($data === null) {
                continue;
            }
            if (array_is_list($data)) {
                foreach ($data as $n => $item) {
                    $out[$name.'#'.($n + 1)] = $item;
                }
            } else {
                $out[$name] = $data;
            }
        }

        return $out;
    }

    /** Normalise a translatable value to a locale map. */
    private function tr(mixed $value): array
    {
        return is_array($value) ? $value : [$this->settings->get('default_locale', 'el') => (string) $value];
    }
}
