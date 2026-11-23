<?php

namespace Tests\Feature;

use App\Http\Controllers\KioskController;
use App\Models\AuditLog;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Progress;
use Tests\TestCase;

class KioskTest extends TestCase
{
    private User $supervisor;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installDemo();
        $this->supervisor = $this->person('supervisor@helios-foods.example');
        $this->worker = User::whereNull('email')->whereNotNull('employee_code')->first();
    }

    private function unlocked(): self
    {
        $this->actingAs($this->supervisor)->post('/kiosk/unlock', ['pin' => '2468'])->assertRedirect('/kiosk');

        return $this;
    }

    public function test_employees_cannot_launch_the_kiosk(): void
    {
        $this->actingAs($this->employee())->get('/kiosk/unlock')->assertForbidden();
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $this->actingAs($this->supervisor)->post('/kiosk/unlock', ['pin' => '0000'])->assertSessionHasErrors('pin');
        $this->assertNull(session('kiosk.active'));
    }

    public function test_kiosk_session_cannot_reach_other_pages_until_pin_is_entered(): void
    {
        $this->unlocked();
        $this->get('/')->assertRedirect('/kiosk');
        $this->get('/admin')->assertRedirect('/kiosk');

        $this->post('/kiosk/exit', ['pin' => '1111'])->assertSessionHasErrors('pin');
        $this->get('/')->assertRedirect('/kiosk');

        $this->post('/kiosk/exit', ['pin' => '2468'])->assertRedirect('/');
        $this->get('/')->assertOk();
    }

    public function test_worker_must_enter_their_own_employee_code_and_is_locked_out_after_repeated_failures(): void
    {
        $this->unlocked();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/kiosk/verify', ['user_id' => $this->worker->id, 'code' => '00000'])->assertStatus(422);
        }
        $this->postJson('/kiosk/verify', ['user_id' => $this->worker->id, 'code' => $this->worker->employee_code])->assertStatus(429);
    }

    public function test_full_supervised_sign_off_is_recorded_under_the_supervisor(): void
    {
        $this->unlocked();
        $content = $this->postJson('/kiosk/verify', ['user_id' => $this->worker->id, 'code' => $this->worker->employee_code])
            ->assertOk()->json();
        $this->assertCount(4, $content['questions']);
        $this->assertEmpty($content['policies']); // shop-floor track does not require policies

        $answers = collect($content['questions'])->mapWithKeys(fn ($q) => [$q['id'] => QuizQuestion::find($q['id'])->correct_option])->all();
        $result = $this->postJson('/kiosk/submit', ['answers' => $answers])->assertOk()->json();

        $this->assertTrue($result['passed']);
        $p = Progress::forUser($this->worker);
        $this->assertSame('complete', $p['status']);
        $this->assertSame($this->supervisor->id, $p['passed_attempt']->supervised_by_user_id);
    }

    public function test_supervisors_print_a_confidential_code_list_that_is_audited(): void
    {
        $workers = KioskController::kioskWorkers();
        $this->assertNotEmpty($workers);
        $department = $workers->first()->department;

        $this->actingAs($this->supervisor)->get('/kiosk/unlock')->assertOk()
            ->assertSee(__('ui.kiosk.codes.title'))->assertSee('action="'.route('kiosk-codes').'"', false);
        $this->actingAs($this->person('hr@helios-foods.example'))->get('/admin/exports')->assertOk()->assertSee(__('ui.kiosk.codes.title'));

        $response = $this->actingAs($this->supervisor)->get('/kiosk-codes')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('kiosk', (string) $response->headers->get('content-disposition'));
        $log = AuditLog::where('action', 'kiosk.codes_printed')->latest('id')->first();
        $this->assertSame($workers->count(), $log->metadata['count']);
        $this->assertFalse($log->metadata['pending_only']);

        $this->actingAs($this->supervisor)->get('/kiosk-codes?pending=1&department='.$department->id)->assertOk();
        $log = AuditLog::where('action', 'kiosk.codes_printed')->latest('id')->first();
        $this->assertSame($department->name, $log->metadata['department']);
        $this->assertSame($workers->where('department_id', $department->id)->count(), $log->metadata['count']);

        $this->actingAs($this->employee())->get('/kiosk-codes')->assertForbidden();
    }

    public function test_code_list_cannot_be_opened_while_the_kiosk_is_active(): void
    {
        $this->unlocked();
        $this->get('/kiosk-codes')->assertRedirect('/kiosk');
        $this->assertFalse(AuditLog::where('action', 'kiosk.codes_printed')->exists());
    }
}
