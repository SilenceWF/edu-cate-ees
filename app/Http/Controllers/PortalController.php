<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Policy;
use App\Models\QuizAttempt;
use App\Models\TrainingModule;
use App\Services\Audit;
use App\Services\Progress;
use App\Services\Training;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The employee's own three-step journey: policies → modules → quiz → certificate. */
class PortalController extends Controller
{
    public function __construct(private Training $training) {}

    public function home(Request $request)
    {
        $progress = Progress::forUser($request->user());

        return view('employee.home', [
            'p' => $progress,
            'campaign' => Campaign::current(),
            'nextModule' => $progress['modules']->first(fn ($m) => ! in_array($m->id, $progress['completed_module_ids'])),
        ]);
    }

    public function policies(Request $request)
    {
        return view('employee.policies', ['p' => Progress::forUser($request->user())]);
    }

    public function signPolicies(Request $request)
    {
        $p = Progress::forUser($request->user());
        if ($p['policies_done']) {
            return redirect()->route('home');
        }

        $required = $p['required_policies'];
        $request->validate(['policies' => 'array', 'declaration' => 'accepted'], [
            'declaration.accepted' => __('ui.policies.must_declare'),
        ]);
        $checked = collect($request->input('policies', []))->map(fn ($id) => (int) $id);

        if ($required->pluck('id')->diff($checked)->isNotEmpty()) {
            return back()->withErrors(['policies' => __('ui.policies.must_ack_all')])->withInput();
        }

        $this->training->signPolicies($request->user(), $required);

        return redirect()->route('home')->with('status', __('ui.policies.signed'));
    }

    public function policyDocument(Request $request, Policy $policy)
    {
        abort_unless($policy->hasDocument(), 404);
        Audit::log('policy.document_viewed', $request->user(), ['policy' => $policy->code]);

        if ($policy->documentIsExternal()) {
            return redirect()->away($policy->document_url);
        }

        $path = storage_path('app/pack/documents/'.basename($policy->document_url));
        abort_unless(is_file($path), 404);

        return response()->file($path);
    }

    public function module(Request $request, TrainingModule $module)
    {
        $p = Progress::forUser($request->user());
        abort_unless($p['modules']->contains('id', $module->id), 404);

        return view('employee.module', [
            'module' => $module,
            'slides' => $module->slidesForDisplay(),
            'done' => in_array($module->id, $p['completed_module_ids']),
            'position' => $p['modules']->search(fn ($m) => $m->id === $module->id) + 1,
            'total' => $p['modules']->count(),
        ]);
    }

    public function completeModule(Request $request, TrainingModule $module)
    {
        $p = Progress::forUser($request->user());
        abort_unless($p['modules']->contains('id', $module->id), 404);

        $this->training->completeModule($request->user(), $module);

        $done = array_merge($p['completed_module_ids'], [$module->id]);
        $next = $p['modules']->first(fn ($m) => ! in_array($m->id, $done));

        return $next
            ? redirect()->route('modules.show', $next)->with('status', __('ui.modules.completed_one'))
            : redirect()->route('home')->with('status', __('ui.modules.completed_all'));
    }

    public function quiz(Request $request)
    {
        $p = Progress::forUser($request->user());
        if (! $p['quiz_unlocked']) {
            return redirect()->route('home')->withErrors(['quiz' => __('ui.quiz.locked')]);
        }

        $questions = $this->training->drawQuestions($p['track']);
        $request->session()->put('quiz', ['ids' => $questions->pluck('id')->all(), 'started_at' => now()->toIso8601String()]);

        return view('employee.quiz', ['questions' => $questions, 'p' => $p]);
    }

    public function submitQuiz(Request $request)
    {
        $p = Progress::forUser($request->user());
        $state = $request->session()->pull('quiz');
        if (! $p['quiz_unlocked'] || ! $state) {
            return redirect()->route('home');
        }

        $attempt = $this->training->gradeQuiz($request->user(), $p['track'], $state['ids'], (array) $request->input('answers', []), $state['started_at']);

        return redirect()->route('quiz.result', $attempt);
    }

    public function quizResult(Request $request, QuizAttempt $attempt)
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        return view('employee.result', [
            'attempt' => $attempt,
            'review' => $this->training->review($attempt),
            'threshold' => (int) setting('quiz.pass_threshold', 80),
        ]);
    }

    public function certificate(Request $request)
    {
        $user = $request->user();
        $p = Progress::forUser($user);
        abort_unless($p['status'] === 'complete', 403);

        Audit::log('certificate.downloaded', $user, ['campaign' => $p['passed_attempt']->campaign_id]);

        return self::certificatePdf($user, $p)->download(Str::slug(__('ui.certificate.filename').'-'.$user->name).'.pdf');
    }

    public static function certificatePdf($user, array $p)
    {
        return Pdf::loadView('pdf.certificate', [
            'user' => $user, 'p' => $p, 'campaign' => Campaign::current(),
            'logo' => BrandController::logoDataUri(),
        ])->setPaper('a4', 'landscape');
    }
}
