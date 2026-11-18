<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GraphMailTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installDemo();
        $this->admin = $this->person('admin@helios-foods.example');
        config([
            'mail.default' => 'graph',
            'mail.from.address' => 'a.michailidis@dryfo.example',
            'mail.from.name' => 'Training',
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'secret',
            'services.microsoft.tenant' => 'tenant-id',
        ]);
        Cache::flush();
        Mail::purge('graph');
    }

    private function fakeGraph(int $sendStatus = 202, array $sendBody = []): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3599]),
            'graph.microsoft.com/*' => Http::response($sendBody, $sendStatus),
        ]);
    }

    private function enable(): void
    {
        app(Settings::class)->set('mail', ['sending_enabled' => true]);
        app(Settings::class)->flush();
    }

    public function test_sends_through_graph_with_a_cached_token(): void
    {
        $this->fakeGraph();

        Mail::raw('Hello', fn ($m) => $m->to('worker@example.com', 'Worker')->subject('Subject one'));
        Mail::raw('Again', fn ($m) => $m->to('worker@example.com')->subject('Subject two'));

        Http::assertSentCount(3); // one token + two messages
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth2/v2.0/token') && str_contains($r->url(), 'tenant-id')
            && $r['grant_type'] === 'client_credentials' && $r['scope'] === 'https://graph.microsoft.com/.default');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.microsoft.com/v1.0/users/a.michailidis%40dryfo.example/sendMail'
            && $r->hasHeader('Authorization', 'Bearer tok')
            && $r['message']['subject'] === 'Subject one'
            && $r['message']['toRecipients'][0]['emailAddress']['address'] === 'worker@example.com'
            && $r['message']['body']['content'] === 'Hello'
            && $r['saveToSentItems'] === true);
    }

    public function test_reminder_uses_mailto_until_sending_is_switched_on(): void
    {
        $this->fakeGraph();
        $this->actingAs($this->admin)->get('/admin')->assertSee('href="mailto:', false);

        $employee = $this->employee();
        $this->actingAs($this->admin)->post("/admin/employees/{$employee->id}/remind")->assertStatus(409);
        Http::assertNothingSent();

        $this->enable();
        $this->actingAs($this->admin)->get('/admin')->assertDontSee('href="mailto:', false);
        $this->actingAs($this->admin)->post("/admin/employees/{$employee->id}/remind")->assertSessionHasNoErrors();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sendMail') && $r['message']['toRecipients'][0]['emailAddress']['address'] === $employee->email);
        $this->assertTrue(AuditLog::where('action', 'reminder.sent')->exists());
    }

    public function test_permission_problem_is_explained(): void
    {
        $this->fakeGraph(403, ['error' => ['code' => 'ErrorAccessDenied', 'message' => 'Access is denied.']]);

        $this->actingAs($this->admin)->post('/admin/settings/mail/test')
            ->assertSessionHasErrors(['mail' => __('admin.mail.errors.access_denied', ['from' => 'a.michailidis@dryfo.example'])]);
        $this->assertTrue(AuditLog::where('action', 'mail.test_failed')->exists());
    }

    public function test_bad_secret_is_explained(): void
    {
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => 'AADSTS7000215: Invalid client secret provided.'], 401)]);

        $this->actingAs($this->admin)->post('/admin/settings/mail/test')
            ->assertSessionHasErrors(['mail' => __('admin.mail.errors.bad_secret')]);
    }

    public function test_test_email_goes_to_the_signed_in_admin(): void
    {
        $this->fakeGraph();

        $this->actingAs($this->admin)->post('/admin/settings/mail/test')->assertSessionHasNoErrors();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sendMail') && $r['message']['toRecipients'][0]['emailAddress']['address'] === $this->admin->email);
    }

    public function test_cannot_switch_on_while_settings_are_missing(): void
    {
        config(['services.microsoft.tenant' => 'common']);

        $this->actingAs($this->admin)->put('/admin/settings/mail', ['enabled' => 1])->assertSessionHasErrors('mail');
        $this->assertFalse((bool) setting('mail.sending_enabled'));

        config(['services.microsoft.tenant' => 'tenant-id']);
        $this->actingAs($this->admin)->put('/admin/settings/mail', ['enabled' => 1])->assertSessionHasNoErrors();
        app(Settings::class)->flush();
        $this->assertTrue((bool) setting('mail.sending_enabled'));
    }

    public function test_hr_cannot_change_mail_settings(): void
    {
        $hr = $this->person('hr@helios-foods.example');
        $this->actingAs($hr)->put('/admin/settings/mail', ['enabled' => 1])->assertForbidden();
        $this->actingAs($hr)->post('/admin/settings/mail/test')->assertForbidden();
    }
}
