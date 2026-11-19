<?php

namespace Tests\Feature;

use App\Mail\TrainingReminderMail;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Training;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderMailTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installDemo();
        $this->admin = $this->person('admin@helios-foods.example');
        config(['mail.default' => 'smtp', 'mail.from.address' => 'training@helios-foods.example']);
    }

    public function test_reminder_is_sent_as_the_branded_mailable(): void
    {
        Mail::fake();
        app(Settings::class)->set('mail', ['sending_enabled' => true]);
        app(Settings::class)->flush();
        $employee = $this->employee();

        $this->actingAs($this->admin)->post("/admin/employees/{$employee->id}/remind")->assertSessionHasNoErrors();

        Mail::assertSent(TrainingReminderMail::class, fn ($m) => $m->hasTo($employee->email) && $m->user->is($employee));
    }

    public function test_email_lists_the_persons_own_modules_deadline_and_progress(): void
    {
        $employee = $this->employee();
        $campaign = Campaign::current();
        $campaign->update(['ends_on' => '2026-12-15']);
        $module = $employee->group->track->modules->first();
        app(Training::class)->completeModule($employee, $module);

        $mail = new TrainingReminderMail($employee->fresh());
        $html = $mail->render();

        $this->assertStringContainsString($employee->name, $html);
        $this->assertStringContainsString(e(loc($module->title, 'el')), $html);
        $this->assertStringContainsString('15/12/2026', $html);
        $this->assertStringContainsString(__('admin.reminder.step_done', [], 'el'), $html);
        $this->assertStringContainsString(url('/'), $html);
        $expectedMinutes = $employee->group->track->modules->sum('estimated_minutes') + 5;
        $this->assertSame($expectedMinutes, $mail->minutes);

        $text = $mail->plainText();
        $this->assertStringContainsString("\n\n", $text, 'paragraphs are separated by a blank line');
        $this->assertStringContainsString($employee->name, $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_shop_floor_worker_sees_their_own_track_without_policies(): void
    {
        $worker = User::whereNull('email')->whereNotNull('employee_code')->first();
        $worker->update(['email' => 'worker@helios-foods.example']);

        $mail = new TrainingReminderMail($worker->fresh());
        $labels = collect($mail->steps)->pluck('label')->implode(' | ');

        $this->assertStringNotContainsString(__('admin.reminder.step_policies', [], 'el'), $labels);
        foreach ($worker->group->track->modules as $m) {
            $this->assertStringContainsString(loc($m->title, 'el'), $labels);
        }
    }

    public function test_english_speaker_gets_english(): void
    {
        $employee = $this->employee();
        $employee->update(['locale' => 'en']);

        $mail = new TrainingReminderMail($employee->fresh());

        $this->assertStringContainsString('Dear '.$employee->name, $mail->render());
        $this->assertStringStartsWith('Reminder:', $mail->envelope()->subject);
    }

    public function test_mailto_link_stays_under_the_windows_limit(): void
    {
        Campaign::current()->update(['ends_on' => '2026-12-15']);
        $href = (new TrainingReminderMail($this->employee()))->mailtoHref();

        $this->assertStringStartsWith('mailto:employee@helios-foods.example?subject=', $href);
        $this->assertLessThan(2000, strlen($href));
    }

    public function test_dashboard_does_not_recompute_progress_for_every_row(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/admin')->assertOk();

            return count(DB::getQueryLog());
        };
        $before = $count();
        User::factory()->count(20)->create(['training_group_id' => $this->employee()->training_group_id]);
        $after = $count();

        $this->assertLessThan(10, $after - $before, 'queries must not grow with the number of people');
    }

    public function test_preview_for_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings/mail/preview')->assertOk()->assertSee($this->admin->name);
        $this->actingAs($this->person('hr@helios-foods.example'))->get('/admin/settings/mail/preview')->assertForbidden();
    }
}
