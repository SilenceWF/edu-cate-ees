<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Department;
use App\Models\Track;
use App\Models\User;
use App\Services\Audit;
use App\Services\Progress;
use App\Services\Training;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Supervised kiosk mode for people without a company account. A supervisor unlocks it
 * with the kiosk PIN; each worker then identifies with their name and employee code.
 * Everything recorded carries supervised_by_user_id.
 */
class KioskController extends Controller
{
    public function __construct(private Training $training) {}

    public function unlockForm(Request $request)
    {
        abort_unless($request->user()->canLaunchKiosk(), 403);
        if ($request->session()->get('kiosk.active')) {
            return redirect()->route('kiosk.index');
        }

        return view('kiosk.unlock', ['departments' => self::kioskDepartments()]);
    }

    public function unlock(Request $request)
    {
        abort_unless($request->user()->canLaunchKiosk(), 403);
        $this->checkPin($request, 'kiosk-unlock:'.$request->user()->id);

        $request->session()->put('kiosk', ['active' => true, 'supervisor_id' => $request->user()->id]);
        Audit::log('kiosk.started', $request->user());

        return redirect()->route('kiosk.index');
    }

    public function index(Request $request)
    {
        if (! $request->session()->get('kiosk.active')) {
            return redirect()->route('kiosk.unlock');
        }
        $request->session()->forget(['kiosk.worker_id', 'kiosk.quiz']);

        $workers = self::kioskWorkers()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'department_id' => $u->department_id])->values();

        return view('kiosk.index', [
            'workers' => $workers,
            'departments' => Department::whereIn('id', $workers->pluck('department_id')->filter()->unique())->orderBy('name')
                ->get()->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name, 'color' => $d->hex_color, 'text' => $d->textColor()]),
        ]);
    }

    /** Worker identifies with their employee code; returns the content for their track. */
    public function verify(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|integer', 'code' => 'required|string|max:32']);
        $supervisor = $this->supervisor($request);
        $key = 'kiosk-code:'.$data['user_id'];

        if (RateLimiter::tooManyAttempts($key, config('portal.kiosk_max_attempts'))) {
            return response()->json(['message' => __('ui.kiosk.too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])], 429);
        }

        $worker = User::where('is_active', true)->find($data['user_id']);
        if (! $worker || ! $worker->employee_code || ! hash_equals((string) $worker->employee_code, trim($data['code']))) {
            RateLimiter::hit($key, 300);
            Audit::log('kiosk.code_rejected', $worker, [], $supervisor);

            return response()->json(['message' => __('ui.kiosk.wrong_code')], 422);
        }
        RateLimiter::clear($key);

        $p = Progress::forUser($worker);
        abort_unless($p['track']?->kiosk_enabled, 403);
        $request->session()->put('kiosk.worker_id', $worker->id);
        Audit::log('kiosk.worker_identified', $worker, [], $supervisor);

        return response()->json([
            'name' => $worker->name,
            'already_complete' => $p['status'] === 'complete',
            'policies' => $p['policies_done'] ? [] : $p['required_policies']->map(fn ($policy) => [
                'code' => $policy->code, 'title' => loc($policy->title),
                'rules' => collect($policy->key_rules)->map(fn ($r) => loc($r))->all(),
            ])->values(),
            'declaration' => loc(setting('declaration')) ?: __('ui.policies.default_declaration'),
            'slides' => $p['modules']->flatMap(fn ($m) => collect($m->slidesForDisplay())->map(fn ($s) => $s + ['module' => loc($m->title)]))->values(),
            'questions' => $this->draw($request, $p['track']),
        ]);
    }

    /** New random set of questions (used for a retry). */
    public function questions(Request $request)
    {
        $worker = $this->worker($request);

        return response()->json(['questions' => $this->draw($request, Progress::forUser($worker)['track'])]);
    }

    public function submit(Request $request)
    {
        $supervisor = $this->supervisor($request);
        $worker = $this->worker($request);
        $state = $request->session()->pull('kiosk.quiz');
        abort_unless($state, 409);

        $p = Progress::forUser($worker);
        if (! $p['policies_done'] && $p['required_policies']->isNotEmpty()) {
            $this->training->signPolicies($worker, $p['required_policies'], $supervisor);
        }
        foreach ($p['modules'] as $module) {
            $this->training->completeModule($worker, $module, $supervisor);
        }

        $attempt = $this->training->gradeQuiz($worker, $p['track'], $state['ids'], (array) $request->input('answers', []), $state['started_at'], $supervisor);
        if ($attempt->passed) {
            Audit::log('kiosk.signed_off', $worker, ['attempt_id' => $attempt->id], $supervisor);
            $request->session()->forget('kiosk.worker_id');
        }

        return response()->json([
            'passed' => $attempt->passed, 'percentage' => (int) round($attempt->percentage),
            'score' => $attempt->score, 'total' => $attempt->total_questions,
            'review' => $this->training->review($attempt),
        ]);
    }

    public function exit(Request $request)
    {
        $this->supervisor($request);
        $this->checkPin($request, 'kiosk-exit:'.$request->session()->getId());

        Audit::log('kiosk.ended', $request->user());
        $request->session()->forget('kiosk');

        return redirect()->route('home');
    }

    private function draw(Request $request, Track $track)
    {
        $questions = $this->training->drawQuestions($track);
        $request->session()->put('kiosk.quiz', ['ids' => $questions->pluck('id')->all(), 'started_at' => now()->toIso8601String()]);

        return $questions->map(fn ($q) => ['id' => $q->id, 'text' => loc($q->question_text), 'options' => $q->localizedOptions()])->values();
    }

    private function supervisor(Request $request): User
    {
        abort_unless($request->session()->get('kiosk.active'), 403);

        return $request->user();
    }

    private function worker(Request $request): User
    {
        $this->supervisor($request);
        $worker = User::find($request->session()->get('kiosk.worker_id'));
        abort_unless($worker, 409);

        return $worker;
    }

    /**
     * A printable list of kiosk workers and their employee codes, for the supervisor running a
     * session (kept apart from the attendance sheet, which everyone signs). Not reachable while
     * the kiosk is active, so a worker at the tablet cannot open it.
     */
    public function codes(Request $request)
    {
        abort_unless($request->user()->canLaunchKiosk(), 403);
        $data = $request->validate(['department' => 'nullable|integer|exists:departments,id', 'pending' => 'nullable|boolean']);
        $department = filled($data['department'] ?? null) ? Department::find($data['department']) : null;
        $campaign = Campaign::current();
        $progress = new Progress($campaign);

        $rows = self::kioskWorkers()->load('department')
            ->when($department, fn (Collection $c) => $c->where('department_id', $department->id))
            ->map(fn (User $u) => ['user' => $u, 'done' => $progress->of($u)['status'] === 'complete'])
            ->when($request->boolean('pending'), fn (Collection $c) => $c->reject(fn ($r) => $r['done']))
            ->sortBy(fn ($r) => mb_strtolower(($r['user']->department?->name ?? "\u{10FFFF}").'|'.$r['user']->name))
            ->values();

        Audit::log('kiosk.codes_printed', $request->user(), [
            'department' => $department?->name, 'pending_only' => $request->boolean('pending'), 'count' => $rows->count(),
        ]);

        return Pdf::loadView('pdf.kiosk-codes', [
            'rows' => $rows, 'department' => $department, 'pendingOnly' => $request->boolean('pending'),
            'campaign' => $campaign, 'printedBy' => $request->user(), 'logo' => BrandController::logoDataUri(),
        ])->setPaper('a4')->download(Str::slug(__('ui.kiosk.codes.filename').'-'.($department?->name ?? 'all')).'-'.now()->format('Ymd').'.pdf');
    }

    /** Active people whose track runs in kiosk mode. */
    public static function kioskWorkers(): Collection
    {
        $trackIds = Track::where('kiosk_enabled', true)->pluck('id');

        return User::where('is_active', true)->with('group')->orderBy('name')->get()
            ->filter(fn (User $u) => $trackIds->contains($u->track_id ?? $u->group?->track_id))->values();
    }

    /** Departments that have kiosk workers, for the code-list filter. */
    public static function kioskDepartments(): Collection
    {
        return Department::whereIn('id', self::kioskWorkers()->pluck('department_id')->filter()->unique())->orderBy('name')->get();
    }

    private function checkPin(Request $request, string $key): void
    {
        $data = $request->validate(['pin' => 'required|string|max:8']);

        if (RateLimiter::tooManyAttempts($key, config('portal.kiosk_max_attempts'))) {
            back()->withErrors(['pin' => __('ui.kiosk.too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])])->throwResponse();
        }
        if (! Hash::check($data['pin'], (string) setting('kiosk.pin_hash'))) {
            RateLimiter::hit($key, 300);
            Audit::log('kiosk.pin_rejected', $request->user());
            back()->withErrors(['pin' => __('ui.kiosk.wrong_pin')])->throwResponse();
        }
        RateLimiter::clear($key);
    }
}
