<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ModuleCompletion;
use App\Models\PolicyAcknowledgment;
use App\Models\PolicyDeclaration;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Track;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records training evidence. Used by both the self-service portal and kiosk mode
 * (where $supervisor is the person who unlocked the kiosk).
 */
class Training
{
    /** Acknowledge every required policy and sign the declaration. */
    public function signPolicies(User $user, Collection $policies, ?User $supervisor = null): void
    {
        $campaign = Campaign::current();
        $request = request();
        $text = loc(setting('declaration')) ?: __('ui.policies.default_declaration');

        DB::transaction(function () use ($user, $policies, $supervisor, $campaign, $request, $text) {
            foreach ($policies as $policy) {
                $ack = PolicyAcknowledgment::firstOrCreate(
                    ['user_id' => $user->id, 'policy_id' => $policy->id, 'campaign_id' => $campaign->id, 'policy_version' => $policy->version],
                    ['acknowledged_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                        'supervised_by_user_id' => $supervisor?->id],
                );
                if ($ack->wasRecentlyCreated) {
                    Audit::log('policy.acknowledged', $user, ['policy' => $policy->code, 'version' => $policy->version, 'campaign' => $campaign->name], $supervisor ?? $user);
                }
            }

            PolicyDeclaration::create([
                'user_id' => $user->id, 'campaign_id' => $campaign->id, 'declaration_text' => $text, 'declared_at' => now(),
                'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'supervised_by_user_id' => $supervisor?->id,
            ]);
            Audit::log('policy.declaration_signed', $user, ['campaign' => $campaign->name, 'policies' => $policies->pluck('code')->all(), 'text' => $text], $supervisor ?? $user);
        });
    }

    public function completeModule(User $user, TrainingModule $module, ?User $supervisor = null): void
    {
        $campaign = Campaign::current();
        $completion = ModuleCompletion::firstOrCreate(
            ['user_id' => $user->id, 'training_module_id' => $module->id, 'campaign_id' => $campaign->id],
            ['completed_at' => now(), 'supervised_by_user_id' => $supervisor?->id],
        );
        if ($completion->wasRecentlyCreated) {
            Audit::log('module.completed', $user, ['module' => $module->key, 'campaign' => $campaign->name], $supervisor ?? $user);
        }
    }

    /** Draw a random set of questions for a track. */
    public function drawQuestions(Track $track): Collection
    {
        return $track->questionPool()->get()->shuffle()->take($track->quiz_question_count)->values();
    }

    /**
     * Grade and store an attempt.
     *
     * @param  int[]  $questionIds  the questions that were shown
     * @param  array<int|string, string>  $answers  question id => chosen letter
     */
    public function gradeQuiz(User $user, Track $track, array $questionIds, array $answers, mixed $startedAt = null, ?User $supervisor = null): QuizAttempt
    {
        $campaign = Campaign::current();
        $questions = QuizQuestion::whereIn('id', $questionIds)->get()->keyBy('id');
        $payload = [];
        $score = 0;

        foreach ($questionIds as $id) {
            $q = $questions->get($id);
            if (! $q) {
                continue;
            }
            $selected = isset($answers[$id]) ? strtoupper((string) $answers[$id]) : null;
            $correct = $selected === $q->correct_option;
            $score += $correct ? 1 : 0;
            $payload[] = ['question_id' => $q->id, 'selected' => $selected, 'correct' => $q->correct_option, 'is_correct' => $correct];
        }

        $total = count($payload);
        $percentage = $total ? round($score / $total * 100, 2) : 0;
        $passed = $percentage >= (float) setting('quiz.pass_threshold', 80);

        $attempt = QuizAttempt::create([
            'user_id' => $user->id, 'campaign_id' => $campaign->id, 'track_id' => $track->id,
            'score' => $score, 'total_questions' => $total, 'percentage' => $percentage, 'passed' => $passed,
            'answers_payload' => $payload, 'started_at' => $startedAt, 'completed_at' => now(),
            'ip_address' => request()->ip(), 'supervised_by_user_id' => $supervisor?->id,
        ]);

        Audit::log($passed ? 'quiz.passed' : 'quiz.failed', $user, [
            'attempt_id' => $attempt->id, 'track' => $track->key, 'score' => "$score/$total", 'percentage' => $percentage, 'campaign' => $campaign->name,
        ], $supervisor ?? $user);

        return $attempt;
    }

    /** Per-question review of an attempt in the current locale. */
    public function review(QuizAttempt $attempt): array
    {
        $questions = QuizQuestion::whereIn('id', collect($attempt->answers_payload)->pluck('question_id'))->get()->keyBy('id');

        return collect($attempt->answers_payload)->map(function ($a) use ($questions) {
            $q = $questions->get($a['question_id']);

            return $q ? [
                'question' => loc($q->question_text), 'options' => $q->localizedOptions(),
                'selected' => $a['selected'], 'correct' => $a['correct'], 'is_correct' => $a['is_correct'],
                'explanation' => loc($q->explanation),
            ] : null;
        })->filter()->values()->all();
    }
}
