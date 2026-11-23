<?php

namespace App\Packs;

use App\Models\ToolkitItem;
use App\Support\Settings;
use Illuminate\Support\Facades\File;

/**
 * Validates and loads ISMS toolkit items described in a toolkit.yaml (from a company pack or
 * from a folder passed to `php artisan toolkit:import`). Items are matched on their code and
 * updated in place; nothing is ever deleted.
 */
class ToolkitImporter
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array  $items  the parsed toolkit.yaml
     * @param  string[]  $knownCodes  codes that already exist elsewhere and may be referenced by 'related'
     * @return string[] every problem found
     */
    public function validate(array $items, string $dir, array $knownCodes = [], string $where = 'in the pack'): array
    {
        if (! array_is_list($items)) {
            return ['toolkit.yaml: must be a list of items (each starting with "- code: …").'];
        }

        $errors = [];
        $codes = array_map(fn ($t) => is_array($t) ? (string) ($t['code'] ?? '') : '', $items);
        foreach ($items as $n => $t) {
            $label = 'toolkit.yaml: item '.($n + 1);
            if (! is_array($t) || blank($t['code'] ?? null) || blank($t['title'] ?? null)) {
                $errors[] = "$label needs 'code' and 'title'.";

                continue;
            }
            $label = "toolkit.yaml: '{$t['code']}'";
            if (count(array_keys($codes, (string) $t['code'], true)) > 1) {
                $errors[] = "$label is listed more than once.";
            }
            if (! in_array($t['type'] ?? null, ToolkitItem::TYPES, true)) {
                $errors[] = "$label: 'type' must be one of ".implode(', ', ToolkitItem::TYPES).'.';
            }
            if (filled($t['file'] ?? null)) {
                $ext = strtolower(pathinfo((string) $t['file'], PATHINFO_EXTENSION));
                if (! in_array($ext, ToolkitItem::TEMPLATE_EXTENSIONS, true)) {
                    $errors[] = "$label: file must be ".implode(', ', ToolkitItem::TEMPLATE_EXTENSIONS).'.';
                } elseif (str_contains((string) $t['file'], '..') || ! is_file($dir.'/'.ltrim((string) $t['file'], '/'))) {
                    $errors[] = "$label: file '{$t['file']}' was not found $where.";
                }
            }
            foreach ((array) ($t['related'] ?? []) as $code) {
                if (! in_array((string) $code, $codes, true) && ! in_array((string) $code, $knownCodes, true)) {
                    $errors[] = "$label: related code '$code' is not in toolkit.yaml.";
                }
            }
        }

        return $errors;
    }

    /** Create or update every item, copying its template into private storage. Returns how many. */
    public function install(array $items, string $dir): int
    {
        foreach (array_values($items) as $i => $t) {
            $file = $this->storeFile($dir, $t['file'] ?? null);
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
            // Like the PIN and pass mark, an item an admin switched off stays off unless the file says otherwise.
            if (array_key_exists('active', $t) || ! $item->exists) {
                $item->is_active = (bool) ($t['active'] ?? true);
            }
            $item->save();
        }

        return count($items);
    }

    /**
     * Work out each item's template file inside a folder of documents (a pack's toolkit/ folder, or the
     * folder given to `toolkit:import`).
     *  - no 'file' key: the file whose name starts with the item's code ("Δ Α5-7 ΔΙΑΔΙΚΑΣΙΑ….docx");
     *  - 'file' with * or ?: a pattern on the file name ("Δ Α5-5*.pdf");
     *  - 'file' empty or false: no template.
     * Matching ignores case, accents, Latin/Greek look-alike capitals (E/Ε, A/Α) and "_" versus spaces.
     * Office lock files (~$…) and other file types are ignored.
     *
     * File paths come back relative to $dir; only $searchDir (default: $dir) is searched, sub-folders included.
     *
     * @return array{0: array, 1: string[], 2: string[]} [items with 'file' filled in, errors, warnings]
     */
    public function findFiles(array $items, string $dir, ?string $searchDir = null): array
    {
        $searchDir ??= $dir;
        $prefix = trim(str_replace('\\', '/', substr(realpath($searchDir) ?: $searchDir, strlen(realpath($dir) ?: $dir))), '/');
        $files = collect(is_dir($searchDir) ? File::allFiles($searchDir) : [])
            ->map(fn ($f) => ltrim($prefix.'/'.str_replace('\\', '/', $f->getRelativePathname()), '/'))
            ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ToolkitItem::TEMPLATE_EXTENSIONS, true))
            ->reject(fn ($path) => str_starts_with(basename($path), '~$') || str_starts_with(basename($path), '.'))
            ->values();

        $errors = $warnings = [];
        foreach ($items as $n => $t) {
            if (! is_array($t) || blank($t['code'] ?? null)) {
                continue;
            }
            $code = (string) $t['code'];
            if (array_key_exists('file', $t) && ! filled($t['file'])) {
                $items[$n]['file'] = null;

                continue;
            }
            $explicit = array_key_exists('file', $t);
            if ($explicit && ! str_contains((string) $t['file'], '*') && ! str_contains((string) $t['file'], '?')) {
                continue; // a plain path, checked by validate()
            }
            $regex = $explicit ? $this->patternRegex((string) $t['file']) : '/^'.preg_quote(self::foldName($code), '/').'(?![\p{L}\p{N}])/u';
            $found = $files->filter(fn ($path) => preg_match($regex, self::foldName(basename($path))))->values();

            if ($found->count() === 1) {
                $items[$n]['file'] = $found->first();
            } elseif ($found->count() > 1) {
                $errors[] = "toolkit.yaml: '$code' matches several files; choose one with file: \"…*…\":\n      ".$found->implode("\n      ");
            } elseif ($explicit) {
                $errors[] = "toolkit.yaml: '$code' file pattern '{$t['file']}' matches no file.";
            } else {
                $items[$n]['file'] = null;
                $warnings[] = "'$code': no file starting with this code; imported without a template.";
            }
        }

        return [$items, $errors, $warnings];
    }

    /** Uppercase, no accents, Greek letters for Latin look-alikes, single spaces for runs of spaces / underscores. */
    public static function foldName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = class_exists(\Normalizer::class)
            ? (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($name, \Normalizer::FORM_D))
            : strtr($name, ['Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω', 'Ϊ' => 'Ι', 'Ϋ' => 'Υ']);
        $name = strtr($name, ['A' => 'Α', 'B' => 'Β', 'E' => 'Ε', 'H' => 'Η', 'I' => 'Ι', 'K' => 'Κ', 'M' => 'Μ', 'N' => 'Ν', 'O' => 'Ο', 'P' => 'Ρ', 'T' => 'Τ', 'X' => 'Χ', 'Y' => 'Υ', 'Z' => 'Ζ']);

        return trim((string) preg_replace('/[\s_]+/u', ' ', $name));
    }

    private function patternRegex(string $pattern): string
    {
        $quoted = preg_quote(self::foldName(basename(str_replace('\\', '/', $pattern))), '/');

        return '/^'.str_replace(['\*', '\?'], ['.*', '.'], $quoted).'$/u';
    }

    private function storeFile(string $dir, ?string $file): ?string
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

    private function tr(mixed $value): array
    {
        return is_array($value) ? $value : [$this->settings->get('default_locale', 'el') => (string) $value];
    }
}
