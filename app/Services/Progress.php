<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ModuleCompletion;
use App\Models\Policy;
use App\Models\PolicyAcknowledgment;
use App\Models\PolicyDeclaration;
use App\Models\QuizAttempt;
use App\Models\SessionAttendance;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Computes where each person stands in a campaign. Loads everything once so it can
 * answer for one user or for the whole roster without a query per person.
 */
class Progress
{
    private Collection $policies;

    private Collection $tracks;

    private Collection $acks;

    private Collection $declarations;

    private Collection $completions;

    private Collection $attempts;

    private Collection $attendance;

    public function __construct(public readonly Campaign $campaign, ?array $userIds = null)
    {
        $this->policies = Policy::active()->get();
        $this->tracks = Track::with(['modules' => fn ($q) => $q->where('is_active', true)])->get()->keyBy('id');

        $scope = fn ($q) => $userIds === null ? $q : $q->whereIn('user_id', $userIds);

        $this->acks = $scope(PolicyAcknowledgment::where('campaign_id', $campaign->id))->get()->groupBy('user_id');
        $this->declarations = $scope(PolicyDeclaration::where('campaign_id', $campaign->id))->get()->groupBy('user_id');
        $this->completions = $scope(ModuleCompletion::where('campaign_id', $campaign->id))->get()->groupBy('user_id');
        $this->attempts = $scope(QuizAttempt::where('campaign_id', $campaign->id))->get()->groupBy('user_id');
        $this->attendance = $scope(SessionAttendance::whereHas('session', fn ($q) => $q->where('campaign_id', $campaign->id)))
            ->get()->groupBy('user_id');
    }

    public static function forUser(User $user, ?Campaign $campaign = null): array
    {
        return (new self($campaign ?? Campaign::current(), [$user->id]))->of($user);
    }

    public function trackOf(User $user): ?Track
    {
        $id = $user->track_id ?? $user->group?->track_id;

        return $id ? $this->tracks->get($id) : null;
    }

    public function of(User $user): array
    {
        $track = $this->trackOf($user);
        $required = $this->policies->filter(fn (Policy $p) => $p->appliesTo($track))->values();
        $acks = $this->acks->get($user->id, collect());
        $acked = $required->filter(fn (Policy $p) => $acks->contains(
            fn ($a) => $a->policy_id === $p->id && $a->policy_version === $p->version
        ));
        $declaration = $this->declarations->get($user->id, collect())->sortByDesc('declared_at')->first();
        $policiesDone = $required->isEmpty() || ($acked->count() === $required->count() && $declaration);

        $modules = $track?->modules ?? collect();
        $done = $this->completions->get($user->id, collect())->keyBy('training_module_id');
        $modulesDone = $modules->every(fn ($m) => $done->has($m->id));
        $modulesDoneAt = $modulesDone && $modules->isNotEmpty()
            ? $modules->map(fn ($m) => $done[$m->id]->completed_at)->max() : null;

        $attempts = $this->attempts->get($user->id, collect());
        $best = $attempts->sortByDesc('percentage')->first();
        $passedAttempt = $attempts->where('passed', true)->sortBy('completed_at')->first();
        $quizPassed = (bool) $passedAttempt;

        $complete = $track && $policiesDone && $modulesDone && $quizPassed;
        $started = $acks->isNotEmpty() || $done->isNotEmpty() || $attempts->isNotEmpty();

        return [
            'track' => $track,
            'required_policies' => $required,
            'acknowledged_ids' => $acked->pluck('id')->all(),
            'acknowledgments' => $acks,
            'declaration' => $declaration,
            'policies_done' => $policiesDone,
            'policies_signed_at' => $required->isEmpty() ? null : $declaration?->declared_at,
            'policies_ip' => $declaration?->ip_address,
            'modules' => $modules,
            'completed_module_ids' => $done->keys()->all(),
            'modules_done' => $modulesDone,
            'modules_done_at' => $modulesDoneAt,
            'attempts' => $attempts,
            'best_attempt' => $best,
            'passed_attempt' => $passedAttempt,
            'quiz_passed' => $quizPassed,
            'quiz_unlocked' => $policiesDone && $modulesDone,
            'attendance' => $this->attendance->get($user->id, collect()),
            'supervised' => (bool) ($passedAttempt?->supervised_by_user_id),
            'status' => $complete ? 'complete' : ($started ? 'in_progress' : 'pending'),
            'percent' => (int) round((($policiesDone ? 1 : 0) + ($modulesDone ? 1 : 0) + ($quizPassed ? 1 : 0)) / 3 * 100),
        ];
    }
}
