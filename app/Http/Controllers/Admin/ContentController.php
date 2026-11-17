<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use App\Models\QuizQuestion;
use App\Models\Track;
use App\Models\TrainingModule;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** In-browser editing of policies, training modules and quiz questions. */
class ContentController extends Controller
{
    private const TYPES = ['policies' => Policy::class, 'modules' => TrainingModule::class, 'questions' => QuizQuestion::class];

    public function index()
    {
        return view('admin.content', [
            'policies' => Policy::orderBy('sort_order')->get(),
            'modules' => TrainingModule::with('tracks')->orderBy('sort_order')->get(),
            'tracks' => Track::orderBy('sort_order')->get(),
            'questions' => QuizQuestion::orderBy('sort_order')->get()->groupBy('track_id'),
        ]);
    }

    public function create(string $type)
    {
        return $this->form($type, new (self::TYPES[$type] ?? abort(404)));
    }

    public function edit(string $type, int $id)
    {
        return $this->form($type, (self::TYPES[$type] ?? abort(404))::findOrFail($id));
    }

    public function store(Request $request, string $type)
    {
        return $this->save($request, $type, new (self::TYPES[$type] ?? abort(404)));
    }

    public function update(Request $request, string $type, int $id)
    {
        return $this->save($request, $type, (self::TYPES[$type] ?? abort(404))::findOrFail($id));
    }

    private function form(string $type, $item)
    {
        return view('admin.content-form', [
            'type' => $type,
            'item' => $item,
            'locales' => setting('locales', ['el']),
            'tracks' => Track::orderBy('sort_order')->get(),
            'rules' => $type === 'policies' ? $this->unzipLines($item->key_rules ?? []) : [],
            'slides' => $type === 'modules' ? collect($item->slides ?? [])->map(fn ($s) => [
                'title' => $this->asMap($s['title'] ?? ''), 'icon' => $s['icon'] ?? '',
                'summary' => $this->asMap($s['summary'] ?? ''), 'bullets' => $this->unzipLines($s['bullets'] ?? []),
                'callout' => $this->asMap($s['callout'] ?? ($s['callout_box'] ?? '')),
            ])->values()->all() : [],
        ]);
    }

    private function save(Request $request, string $type, $item)
    {
        $default = setting('default_locale', 'el');
        $request->validate(["title.$default" => $type === 'questions' ? 'nullable' : 'required|string'], [], ["title.$default" => __('admin.content.title')]);

        match ($type) {
            'policies' => $this->savePolicy($request, $item),
            'modules' => $this->saveModule($request, $item),
            'questions' => $this->saveQuestion($request, $item),
        };

        Audit::log('content.'.($item->wasRecentlyCreated ? 'created' : 'updated'), null, [
            'type' => $type, 'id' => $item->id, 'ref' => $item->code ?? $item->key ?? null, 'changed' => array_keys($item->getChanges()),
        ]);

        return redirect()->route('admin.content')->with('status', __('admin.content.saved'));
    }

    private function savePolicy(Request $request, Policy $policy): void
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('policies')->ignore($policy->id)],
            'version' => 'required|string|max:20',
            'effective_date' => 'nullable|date',
            'document_url' => 'nullable|url|max:500',
            'document_file' => 'nullable|file|mimes:pdf|max:20480',
            'track_keys' => 'array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $document = $data['document_url'] ?? ($policy->documentIsExternal() ? null : $policy->document_url);
        if ($request->hasFile('document_file')) {
            File::ensureDirectoryExists(storage_path('app/pack/documents'));
            $document = Str::slug($data['code']).'-'.Str::slug($data['version']).'.pdf';
            $request->file('document_file')->move(storage_path('app/pack/documents'), $document);
        }

        $policy->fill([
            'code' => $data['code'], 'title' => $this->tr($request, 'title'), 'version' => $data['version'],
            'effective_date' => $data['effective_date'] ?? null, 'document_url' => $document,
            'key_rules' => $this->zipLines((array) $request->input('rules', [])),
            'is_mandatory_all' => $request->boolean('is_mandatory_all'),
            'track_keys' => $request->boolean('is_mandatory_all') ? null : array_values($data['track_keys'] ?? []),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? ($policy->sort_order ?: (int) Policy::max('sort_order') + 1),
        ])->save();
    }

    private function saveModule(Request $request, TrainingModule $module): void
    {
        $data = $request->validate([
            'key' => [$module->exists ? 'nullable' : 'required', 'alpha_dash', 'max:60', Rule::unique('training_modules')->ignore($module->id)],
            'estimated_minutes' => 'required|integer|min:1|max:240',
            'slides_json' => 'required|json',
            'tracks' => 'array',
            'tracks.*' => 'exists:tracks,id',
        ]);

        $slides = collect(json_decode($data['slides_json'], true))->map(fn ($s) => array_filter([
            'title' => $this->clean($s['title'] ?? []), 'icon' => trim((string) ($s['icon'] ?? '')) ?: null,
            'summary' => $this->clean($s['summary'] ?? []), 'bullets' => $this->zipLines($s['bullets'] ?? []),
            'callout' => $this->clean($s['callout'] ?? []),
        ]))->filter(fn ($s) => ! empty($s['title']))->values()->all();
        abort_if(! $slides, 422, __('admin.content.need_slide'));

        $module->fill([
            'title' => $this->tr($request, 'title'), 'subtitle' => $this->tr($request, 'subtitle') ?: null,
            'estimated_minutes' => $data['estimated_minutes'], 'slides' => $slides,
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! $module->exists) {
            $module->key = $data['key'];
            $module->sort_order = (int) TrainingModule::max('sort_order') + 1;
        }
        $module->save();

        // Keep the existing order within each track; new assignments go last.
        $wanted = collect($data['tracks'] ?? [])->map(fn ($id) => (int) $id);
        foreach (Track::with('modules')->get() as $track) {
            $has = $track->modules->contains('id', $module->id);
            if ($wanted->contains($track->id) && ! $has) {
                $track->modules()->attach($module->id, ['sort_order' => (int) $track->modules->max('pivot.sort_order') + 1]);
            } elseif (! $wanted->contains($track->id) && $has) {
                $track->modules()->detach($module->id);
            }
        }
    }

    private function saveQuestion(Request $request, QuizQuestion $question): void
    {
        $default = setting('default_locale', 'el');
        $data = $request->validate([
            'track_id' => 'required|exists:tracks,id',
            "question_text.$default" => 'required|string',
            "options.A.$default" => 'required|string',
            "options.B.$default" => 'required|string',
            'correct_option' => 'required|in:A,B,C,D',
        ]);

        $options = collect((array) $request->input('options', []))->map(fn ($o) => $this->clean($o))->filter()->all();
        abort_unless(isset($options[$data['correct_option']]), 422, __('admin.content.correct_missing'));

        $question->fill([
            'track_id' => $data['track_id'], 'question_text' => $this->tr($request, 'question_text'), 'options' => $options,
            'correct_option' => $data['correct_option'], 'explanation' => $this->tr($request, 'explanation') ?: null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $question->sort_order ?: (int) QuizQuestion::where('track_id', $data['track_id'])->max('sort_order') + 1,
        ])->save();
    }

    private function tr(Request $request, string $field): array
    {
        return $this->clean((array) $request->input($field, []));
    }

    private function clean(mixed $map): array
    {
        return collect((array) $map)->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '')->all();
    }

    private function asMap(mixed $value): array
    {
        return is_array($value) ? $value : [setting('default_locale', 'el') => (string) $value];
    }

    /** ['el' => "a\nb", 'en' => "x\ny"] → [['el' => 'a', 'en' => 'x'], ['el' => 'b', 'en' => 'y']] */
    private function zipLines(array $perLocale): array
    {
        $lines = collect($perLocale)->map(fn ($text) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text)), fn ($l) => $l !== '')));
        $out = [];
        for ($i = 0; $i < ($lines->map(fn ($l) => count($l))->max() ?? 0); $i++) {
            $out[] = $lines->map(fn ($l) => $l[$i] ?? null)->filter()->all();
        }

        return $out;
    }

    /** The reverse of zipLines, for showing a list in one textarea per language. */
    private function unzipLines(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            foreach ($this->asMap($item) as $locale => $text) {
                $out[$locale][] = $text;
            }
        }

        return array_map(fn ($lines) => implode("\n", $lines), $out);
    }
}
