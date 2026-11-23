<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Policy;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Services\Progress;
use App\Support\Settings;
use Tests\TestCase;

class EmployeeJourneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['portal.local_auth' => true]);
        $this->installDemo();
    }

    private function signPolicies($user): void
    {
        $ids = Progress::forUser($user)['required_policies']->pluck('id')->all();
        $this->actingAs($user)->post('/policies', ['policies' => $ids, 'declaration' => '1'])->assertRedirect('/');
    }

    private function completeModules($user): void
    {
        foreach (Progress::forUser($user)['modules'] as $module) {
            $this->actingAs($user)->post("/modules/{$module->id}/complete")->assertRedirect();
        }
    }

    /** Take the quiz answering $correct questions correctly. */
    private function takeQuiz($user, int $correct): QuizAttempt
    {
        $this->actingAs($user)->get('/quiz')->assertOk();
        $answers = [];
        foreach (session('quiz.ids') as $n => $id) {
            $q = QuizQuestion::find($id);
            $answers[$id] = $n < $correct ? $q->correct_option : collect(array_keys($q->options))->first(fn ($l) => $l !== $q->correct_option);
        }
        $this->actingAs($user)->post('/quiz', ['answers' => $answers])->assertRedirect();

        return QuizAttempt::latest('id')->first();
    }

    public function test_local_login_works_and_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['email' => 'employee@helios-foods.example', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'employee@helios-foods.example', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_language_switch_is_offered_when_signed_in_even_for_a_single_language_pack(): void
    {
        app(Settings::class)->set('locales', ['el']);
        app(Settings::class)->flush();
        $user = $this->employee();

        $this->actingAs($user)->get('/')->assertSee('action="'.route('locale').'"', false)
            ->assertSee('value="el"', false)->assertSee('value="en"', false)
            ->assertSee(__('ui.nav.my_training', [], 'el'));

        $this->actingAs($user)->from('/')->post('/locale', ['locale' => 'en'])->assertRedirect('/');
        $this->assertSame('en', $user->fresh()->locale);
        $this->actingAs($user->fresh())->get('/')->assertSee(__('ui.nav.my_training', [], 'en'));

        $this->actingAs($user)->post('/locale', ['locale' => 'xx']);
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_policies_require_every_acknowledgment_and_the_declaration(): void
    {
        $user = $this->employee();
        $ids = Policy::pluck('id')->all();

        $this->actingAs($user)->post('/policies', ['policies' => array_slice($ids, 1), 'declaration' => '1'])->assertSessionHasErrors('policies');
        $this->actingAs($user)->post('/policies', ['policies' => $ids])->assertSessionHasErrors('declaration');

        $this->signPolicies($user);
        $p = Progress::forUser($user);
        $this->assertTrue($p['policies_done']);
        $this->assertSame(9, $p['acknowledgments']->count());
        $this->assertNotNull($p['acknowledgments']->first()->ip_address);
    }

    public function test_quiz_stays_locked_until_policies_and_modules_are_done(): void
    {
        $user = $this->employee();
        $this->actingAs($user)->get('/quiz')->assertRedirect('/');

        $this->signPolicies($user);
        $this->actingAs($user)->get('/quiz')->assertRedirect('/');

        $this->completeModules($user);
        $this->actingAs($user)->get('/quiz')->assertOk();
    }

    public function test_pass_mark_is_eighty_percent_and_certificate_unlocks_on_pass(): void
    {
        $user = $this->employee();
        $this->signPolicies($user);
        $this->completeModules($user);

        $attempt = $this->takeQuiz($user, 6);           // 6/8 = 75%
        $this->assertFalse($attempt->passed);
        $this->actingAs($user)->get('/certificate')->assertForbidden();
        $this->actingAs($user)->get("/quiz/attempts/{$attempt->id}")->assertOk()->assertSee(__('ui.quiz.retry'));

        $attempt = $this->takeQuiz($user, 7);           // 7/8 = 87.5%
        $this->assertTrue($attempt->passed);
        $this->assertSame('complete', Progress::forUser($user)['status']);

        $response = $this->actingAs($user)->get('/certificate');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_quiz_cannot_be_submitted_with_questions_that_were_not_served(): void
    {
        $user = $this->employee();
        $this->signPolicies($user);
        $this->completeModules($user);
        $this->actingAs($user)->get('/quiz');
        $served = session('quiz.ids');
        $other = QuizQuestion::whereNotIn('id', $served)->first();

        // Answers for questions that were not served are ignored; the served ones count as unanswered.
        $this->actingAs($user)->post('/quiz', ['answers' => [$other->id => $other->correct_option]]);
        $this->assertSame(0, QuizAttempt::latest('id')->first()->score);
    }

    public function test_new_policy_version_asks_for_acknowledgment_again(): void
    {
        $user = $this->employee();
        $this->signPolicies($user);
        Policy::where('code', 'POL-02')->update(['version' => '2']);

        $p = Progress::forUser($user);
        $this->assertFalse($p['policies_done']);
        $this->assertCount(8, $p['acknowledged_ids']);
    }

    public function test_a_new_campaign_requires_training_again_and_keeps_history(): void
    {
        $user = $this->employee();
        $this->signPolicies($user);
        $old = Campaign::current();

        Campaign::create(['name' => '2027'])->makeCurrent();

        $this->assertFalse(Progress::forUser($user)['policies_done']);
        $this->assertTrue(Progress::forUser($user, $old->fresh())['policies_done']);
    }

    public function test_people_only_see_their_own_attempts(): void
    {
        $user = $this->employee();
        $this->signPolicies($user);
        $this->completeModules($user);
        $attempt = $this->takeQuiz($user, 8);

        $this->actingAs($this->person('hr@helios-foods.example'))->get("/quiz/attempts/{$attempt->id}")->assertForbidden();
    }

    public function test_certificate_stays_on_one_page_with_long_company_texts(): void
    {
        $settings = app(Settings::class);
        $settings->set('company', array_merge((array) $settings->get('company'), [
            'legal_name' => 'Εταιρεία Δοκιμών Μακράς Επωνυμίας Ανώνυμη Βιομηχανική Εμπορική Εταιρεία Α.Β.Ε.Ε.',
            'frameworks' => 'ISO/IEC 27001:2022 (§7.2, §7.3, Παράρτημα Α 6.3) · Ν. 5160/2024 (NIS2) · ΚΥΑ 1689/2025',
            'signatory' => ['name' => 'Αλέξανδρος Μιχαηλίδης', 'title' => 'Υπεύθυνος Ασφάλειας Συστημάτων Πληροφορικής και Επικοινωνιών (Υ.Α.Σ.Π.Ε.)'],
        ]));
        $settings->flush();

        $user = $this->employee();
        $this->signPolicies($user);
        $this->completeModules($user);
        $this->takeQuiz($user, 8);

        $pdf = $this->actingAs($user)->get('/certificate')->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'certificate must be exactly one page');
    }
}
