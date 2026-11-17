<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Track;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Imports people from the training-plan spreadsheet (or a pack's people.csv).
 *
 * Spreadsheet columns: A group, B department (cell fill colour = org-chart colour),
 * C full name, D training type note, E work email, F employee code (optional), G role (optional).
 *
 * Existing people are matched by email, then by name, and only their roster fields are
 * updated, so training progress is never touched by a re-import.
 */
class RosterImporter
{
    /** Read a spreadsheet into normalised rows. */
    public function readSpreadsheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $i = $row->getRowIndex();
            $cell = fn (string $col) => trim((string) $sheet->getCell($col.$i)->getFormattedValue());
            if ($cell('C') === '') {
                continue;
            }

            $fill = $sheet->getStyle('B'.$i)->getFill();
            $rgb = $fill->getFillType() && $fill->getFillType() !== 'none' ? strtoupper((string) $fill->getStartColor()->getRGB()) : '';
            $color = preg_match('/^[0-9A-F]{6}$/', $rgb) && ! in_array($rgb, ['000000', 'FFFFFF'], true) ? '#'.$rgb : null;

            $rows[] = [
                'line' => $i,
                'group' => $cell('A'), 'department' => $cell('B'), 'name' => $cell('C'), 'note' => $cell('D'),
                'email' => strtolower($cell('E')) ?: null, 'employee_code' => $cell('F') ?: null,
                'role' => strtolower($cell('G')) ?: null, 'color' => $color,
            ];
        }

        return $rows;
    }

    /** Read a people.csv (header row: group,department,name,note,email,employee_code,role,location,color,password). */
    public function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\u{FEFF}")), fgetcsv($handle, null, ',', '"', '') ?: []);
        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $line++;
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = array_combine($header, array_pad(array_map('trim', $data), count($header), ''));
            $rows[] = [
                'line' => $line,
                'group' => $r['group'] ?? '', 'department' => $r['department'] ?? '', 'name' => $r['name'] ?? '',
                'note' => $r['note'] ?? '', 'email' => strtolower($r['email'] ?? '') ?: null,
                'employee_code' => ($r['employee_code'] ?? '') ?: null, 'role' => strtolower($r['role'] ?? '') ?: null,
                'color' => ($r['color'] ?? '') ?: null, 'location' => ($r['location'] ?? '') ?: null,
                'password' => ($r['password'] ?? '') ?: null,
            ];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Work out what an import would do, without writing anything.
     *
     * @return array{rows: array, errors: array, summary: array}
     */
    public function plan(array $rows): array
    {
        $groups = TrainingGroup::with('track')->get();
        $tracks = Track::all()->keyBy('key');
        $users = User::all();
        $out = [];
        $errors = [];

        foreach ($rows as $row) {
            $group = $this->matchGroup($groups, $row['group']);
            $track = $this->trackForNote($row['note'] ?? '', $tracks) ?? $group?->track;
            $existing = ($row['email'] ? $users->firstWhere('email', $row['email']) : null)
                ?? $users->first(fn (User $u) => $this->norm($u->name) === $this->norm($row['name'])
                    && (! $row['email'] || ! $u->email));

            if ($row['role'] && ! in_array($row['role'], User::ROLES, true)) {
                $errors[] = __('admin.import.bad_role', ['line' => $row['line'], 'role' => $row['role']]);
                $row['role'] = null;
            }
            if (! $group && $row['group'] === '') {
                $errors[] = __('admin.import.no_group', ['line' => $row['line']]);
            }

            $out[] = $row + [
                'action' => $existing ? 'update' : 'create',
                'user_id' => $existing?->id,
                'group_id' => $group?->id,
                'new_group' => ! $group && $row['group'] !== '',
                'track_key' => $track?->key ?? setting('import.default_track'),
            ];
        }

        return [
            'rows' => $out,
            'errors' => $errors,
            'summary' => [
                'create' => collect($out)->where('action', 'create')->count(),
                'update' => collect($out)->where('action', 'update')->count(),
                'new_groups' => collect($out)->where('new_group', true)->pluck('group')->unique()->values()->all(),
            ],
        ];
    }

    /** Apply an import. Returns the same summary as plan(). */
    public function apply(array $rows): array
    {
        $plan = $this->plan($rows);
        $tracks = Track::all()->keyBy('key');

        DB::transaction(function () use ($plan, $tracks) {
            foreach ($plan['rows'] as $row) {
                $track = $tracks->get($row['track_key']) ?? $tracks->first();

                $group = $row['group_id'] ? TrainingGroup::find($row['group_id']) : null;
                if (! $group && $row['group'] !== '') {
                    $group = TrainingGroup::firstOrCreate(
                        ['key' => Str::slug($row['group']) ?: 'group-'.Str::random(6)],
                        ['name' => [setting('default_locale', 'el') => $row['group']], 'track_id' => $track->id,
                            'sort_order' => (int) TrainingGroup::max('sort_order') + 1],
                    );
                }

                $department = null;
                if ($row['department'] !== '') {
                    $department = Department::firstOrNew(['name' => $row['department']]);
                    if (! empty($row['color'])) {
                        $department->hex_color = strtoupper($row['color']);
                    }
                    if (! empty($row['location'])) {
                        $department->location = $row['location'];
                    } elseif (! $department->location) {
                        $department->location = $this->guessLocation($row['department']);
                    }
                    $department->save();
                }

                $user = $row['user_id'] ? User::find($row['user_id']) : new User(['role' => 'employee']);
                $user->fill(array_filter([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'employee_code' => $row['employee_code'],
                    'role' => $row['role'],
                    'training_requirement_note' => $row['note'] ?: null,
                ], fn ($v) => $v !== null));
                $user->training_group_id = $group?->id ?? $user->training_group_id;
                $user->department_id = $department?->id ?? $user->department_id;
                $user->track_id = ($track && $group && $track->id !== $group->track_id) ? $track->id : null;
                if (! empty($row['password'])) {
                    $user->password = $row['password'];
                }
                if (! $user->employee_code && $track?->kiosk_enabled) {
                    $user->employee_code = $this->newEmployeeCode();
                }
                $user->save();
            }
        });

        return $plan;
    }

    public function newEmployeeCode(): string
    {
        do {
            $code = (string) random_int(10000, 99999);
        } while (User::where('employee_code', $code)->exists());

        return $code;
    }

    private function matchGroup($groups, string $value): ?TrainingGroup
    {
        $needle = $this->norm($value);
        if ($needle === '') {
            return null;
        }

        return $groups->first(fn (TrainingGroup $g) => $this->norm($g->key) === $needle
            || collect($g->name)->contains(fn ($n) => $this->norm($n) === $needle))
            // "Group 7 - ..." style labels: fall back to matching the leading "Group N".
            ?? (preg_match('/^(group\s*\d+)\b/u', $needle, $m)
                ? $groups->first(fn (TrainingGroup $g) => collect($g->name)->contains(
                    fn ($n) => preg_match('/^'.preg_quote($m[1], '/').'\b/u', $this->norm($n))))
                : null);
    }

    private function trackForNote(string $note, $tracks): ?Track
    {
        if ($note === '') {
            return null;
        }
        foreach (setting('import.track_rules', []) as $rule) {
            if (mb_stripos($note, (string) ($rule['contains'] ?? '')) !== false && $tracks->has($rule['track'] ?? '')) {
                return $tracks->get($rule['track']);
            }
        }

        return $tracks->get(setting('import.default_track'));
    }

    private function guessLocation(string $department): ?string
    {
        return str_contains($department, ' - ') ? trim(Str::afterLast($department, ' - ')) : null;
    }

    private function norm(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
    }
}
