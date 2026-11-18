<?php

namespace App\Packs;

use App\Models\Department;
use App\Models\Policy;
use App\Models\QuizQuestion;
use App\Models\Track;
use App\Models\TrainingGroup;
use App\Models\TrainingModule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * Writes the current content (as edited in the admin UI) back out as a pack zip.
 * People are deliberately not exported: a pack carries content, not personal data.
 */
class PackExporter
{
    public function export(): string
    {
        $slug = setting('pack.slug', 'company');
        $dir = storage_path('app/pack-exports/'.$slug.'-'.now()->format('Ymd-His'));
        foreach (['policies', 'modules', 'quizzes', 'branding'] as $sub) {
            File::ensureDirectoryExists("$dir/$sub");
        }

        $manifest = [
            'format' => 1,
            'slug' => $slug,
            'version' => now()->format('Y.m.d'),
            'company' => setting('company', []),
            'locales' => setting('locales', ['el', 'en']),
            'default_locale' => setting('default_locale', 'el'),
            'timezone' => setting('timezone', 'Europe/Athens'),
            'branding' => ['colors' => setting('branding.colors', [])],
            'quiz' => ['pass_threshold' => setting('quiz.pass_threshold', 80)],
            'declaration' => setting('declaration'),
            'import' => setting('import', []),
            'locations' => setting('locations', []),
        ];
        if ($logo = setting('branding.logo')) {
            if (is_file(storage_path('app/pack/branding/'.$logo))) {
                File::copy(storage_path('app/pack/branding/'.$logo), "$dir/branding/$logo");
                $manifest['branding']['logo'] = 'branding/'.$logo;
            }
        }
        if ($favicon = setting('branding.favicon')) {
            if (is_file(storage_path('app/pack/branding/'.$favicon))) {
                File::copy(storage_path('app/pack/branding/'.$favicon), "$dir/branding/$favicon");
                $manifest['branding']['favicon'] = 'branding/'.$favicon;
            }
        }
        $this->write("$dir/pack.yaml", $manifest);

        $this->write("$dir/tracks.yaml", Track::with('modules')->orderBy('sort_order')->get()->map(fn (Track $t) => array_filter([
            'key' => $t->key, 'name' => $t->name, 'quiz_questions' => $t->quiz_question_count,
            'quiz_from' => $t->quiz_from, 'policies' => $t->requires_policies, 'kiosk' => $t->kiosk_enabled,
            'modules' => $t->modules->pluck('key')->all(),
        ], fn ($v) => $v !== null))->all());

        $this->write("$dir/groups.yaml", TrainingGroup::with('track')->orderBy('sort_order')->get()->map(fn (TrainingGroup $g) => array_filter([
            'key' => $g->key, 'name' => $g->name, 'track' => $g->track->key, 'description' => $g->description,
        ]))->all());

        $this->write("$dir/departments.yaml", Department::orderBy('name')->get()->map(fn (Department $d) => array_filter([
            'name' => $d->name, 'location' => $d->location, 'color' => $d->hex_color,
        ]))->all());

        foreach (Policy::ordered(Policy::active()) as $i => $p) {
            $document = $p->document_url;
            if ($document && ! $p->documentIsExternal() && is_file(storage_path('app/pack/documents/'.$document))) {
                File::ensureDirectoryExists("$dir/documents");
                File::copy(storage_path('app/pack/documents/'.$document), "$dir/documents/$document");
                $document = 'documents/'.$document;
            }
            $this->write(sprintf('%s/policies/%02d-%s.yaml', $dir, $i + 1, Str::slug($p->code) ?: 'policy'), array_filter([
                'code' => $p->code, 'title' => $p->title, 'version' => $p->version,
                'effective_date' => $p->effective_date?->toDateString(), 'document' => $document,
                'mandatory_all' => $p->is_mandatory_all, 'tracks' => $p->track_keys, 'key_rules' => $p->key_rules,
            ], fn ($v) => $v !== null));
        }

        foreach (TrainingModule::where('is_active', true)->orderBy('sort_order')->get() as $i => $m) {
            $this->write(sprintf('%s/modules/%02d-%s.yaml', $dir, $i + 1, $m->key), array_filter([
                'key' => $m->key, 'title' => $m->title, 'subtitle' => $m->subtitle,
                'minutes' => $m->estimated_minutes, 'slides' => $m->slides,
            ], fn ($v) => $v !== null));
        }

        foreach (Track::all() as $t) {
            $questions = QuizQuestion::where('track_id', $t->id)->where('is_active', true)->orderBy('sort_order')->get();
            if ($questions->isEmpty()) {
                continue;
            }
            $this->write("$dir/quizzes/{$t->key}.yaml", $questions->map(fn (QuizQuestion $q) => array_filter([
                'question' => $q->question_text, 'options' => $q->options,
                'correct' => $q->correct_option, 'explanation' => $q->explanation,
            ]))->all());
        }

        $zipPath = $dir.'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (File::allFiles($dir) as $file) {
            $zip->addFile($file->getPathname(), $slug.'/'.str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->close();
        File::deleteDirectory($dir);

        return $zipPath;
    }

    private function write(string $path, array $data): void
    {
        File::put($path, Yaml::dump($data, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }
}
