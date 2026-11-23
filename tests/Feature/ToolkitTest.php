<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ToolkitItem;
use App\Models\ToolkitSubmission;
use App\Models\User;
use App\Packs\PackException;
use App\Packs\PackExporter;
use App\Packs\PackLoader;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use ZipArchive;

class ToolkitTest extends TestCase
{
    private string $storage;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        // Template and submission files go to a throw-away storage folder.
        $this->storage = sys_get_temp_dir().'/toolkit-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);

        $this->installDemo();
        $this->supervisor = $this->person('supervisor@helios-foods.example');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function item(string $code): ToolkitItem
    {
        return ToolkitItem::where('code', $code)->firstOrFail();
    }

    private function upload(string $name = 'filled.xlsx', string $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 40, $mime);
    }

    private function graph(bool $badSecret = false): void
    {
        config([
            'mail.default' => 'graph',
            'mail.from.address' => 'training@helios-foods.example',
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'secret',
            'services.microsoft.tenant' => 'tenant-id',
        ]);
        Cache::flush();
        Mail::purge('graph');
        app(Settings::class)->set('mail', ['sending_enabled' => true]);
        app(Settings::class)->flush();
        Http::fake([
            'login.microsoftonline.com/*' => $badSecret
                ? Http::response(['error' => 'invalid_client'], 401)
                : Http::response(['access_token' => 'tok', 'expires_in' => 3599]),
            'graph.microsoft.com/*' => Http::response([], 202),
        ]);
    }

    public function test_access_by_role_or_by_an_admin_grant(): void
    {
        $admin = $this->person('admin@helios-foods.example');
        $hr = $this->person('hr@helios-foods.example');
        $employee = $this->employee();

        foreach ([$admin, $hr, $this->supervisor] as $user) {
            $this->actingAs($user)->get('/toolkit')->assertOk();
            $this->actingAs($user)->get('/')->assertSee(route('toolkit.index'), false);
        }
        $this->actingAs($employee)->get('/toolkit')->assertForbidden();
        $this->actingAs($employee)->get('/')->assertDontSee(route('toolkit.index'), false);
        $this->actingAs($employee)->get('/toolkit/'.$this->item('FRM-05')->id.'/download')->assertForbidden();

        // HR maintains the roster but cannot grant toolkit access.
        $form = ['name' => $employee->name, 'email' => $employee->email, 'training_group_id' => $employee->training_group_id, 'is_active' => 1, 'can_access_toolkit' => 1];
        $this->actingAs($hr)->put("/admin/employees/{$employee->id}", $form)->assertRedirect();
        $this->assertFalse($employee->fresh()->canAccessToolkit());

        $this->actingAs($admin)->get("/admin/employees/{$employee->id}/edit")->assertSee('name="can_access_toolkit"', false);
        $this->actingAs($hr)->get("/admin/employees/{$employee->id}/edit")->assertDontSee('name="can_access_toolkit"', false);
        $this->actingAs($admin)->put("/admin/employees/{$employee->id}", $form)->assertRedirect();
        $this->assertTrue($employee->fresh()->canAccessToolkit());
        $this->actingAs($employee->fresh())->get('/toolkit')->assertOk();
        $this->assertTrue(AuditLog::where('action', 'user.updated')->get()->contains(fn ($l) => $l->metadata['after']['can_access_toolkit'] === true));
    }

    public function test_items_are_listed_in_code_order_with_guidance_and_filters(): void
    {
        ToolkitItem::create(['code' => 'FRM-10', 'type' => 'form', 'title' => ['el' => 'Δέκατο έντυπο']]);
        ToolkitItem::create(['code' => 'FRM-9', 'type' => 'form', 'title' => ['el' => 'Ένατο έντυπο'], 'related_codes' => ['PR-05', 'XX-99']]);
        ToolkitItem::create(['code' => 'FRM-99', 'type' => 'form', 'title' => ['el' => 'Ανενεργό'], 'is_active' => false]);

        // Natural code order: FRM-9 before FRM-10.
        $response = $this->actingAs($this->supervisor)->get('/toolkit')->assertOk()
            ->assertSeeInOrder(['FRM-05', 'FRM-9', 'FRM-10', 'GD-01', 'PR-05'])
            ->assertDontSee('Ανενεργό')
            ->assertSee('Ερωτηματολόγιο Αξιολόγησης Προμηθευτή')
            ->assertSee(__('ui.toolkit.when_to_use'))
            ->assertSee(__('ui.toolkit.roles'))
            ->assertSee(__('ui.toolkit.example'))
            ->assertSee('href="#item-'.$this->item('PR-05')->id.'"', false) // related pill jumps to the linked item
            ->assertSee(__('ui.toolkit.download'))
            ->assertSee(__('ui.toolkit.submit'));
        $this->assertSame(['form' => 3, 'procedure' => 1, 'guide' => 1], $response->viewData('counts'));
        $this->assertCount(5, $response->viewData('visibleIds'));

        $visible = fn (string $query) => ToolkitItem::whereIn('id', $this->actingAs($this->supervisor)->get('/toolkit?'.$query)->viewData('visibleIds'))
            ->pluck('code')->sort()->values()->all();
        $this->assertSame(['FRM-05', 'FRM-10', 'FRM-9'], $visible('type=form'));
        $this->assertSame(['PR-05'], $visible('type=procedure'));
        $this->assertSame(['FRM-05', 'PR-05'], $visible('q='.rawurlencode('ΠΡΟΜΗΘΕΥΤΗ'))); // case- and accent-insensitive
        $this->assertSame(['FRM-05'], $visible('type=form&q='.rawurlencode('προμηθευτή ερωτηματολόγιο')));
        $this->assertSame([], $visible('q=nothing-like-this'));
        $this->assertCount(5, $visible('type=bogus'));
    }

    public function test_template_download_is_a_fresh_dated_copy_and_is_audited(): void
    {
        $item = $this->item('FRM-05');
        $master = $item->filePath();
        $hash = sha1_file($master);

        $response = $this->actingAs($this->supervisor)->get("/toolkit/{$item->id}/download")->assertOk();
        $response->assertDownload('frm-05-'.now()->timezone('Europe/Athens')->format('Y-m-d').'.xlsx');
        $this->assertSame($hash, sha1_file($master), 'the master template is never modified');

        $log = AuditLog::where('action', 'toolkit.downloaded')->latest('id')->first();
        $this->assertSame(['code' => 'FRM-05', 'type' => 'form'], $log->metadata);
        $this->assertSame($this->supervisor->id, $log->target_user_id);

        // No template file, or an inactive item: nothing to download.
        $this->actingAs($this->supervisor)->get('/toolkit/'.$this->item('PR-05')->id.'/download')->assertNotFound();
        $item->update(['is_active' => false]);
        $this->actingAs($this->supervisor)->get("/toolkit/{$item->id}/download")->assertNotFound();
    }

    public function test_submission_is_kept_audited_and_offers_mailto_while_sending_is_off(): void
    {
        Mail::fake();
        $item = $this->item('FRM-05');

        $this->actingAs($this->supervisor)->post("/toolkit/{$item->id}/submit", [
            'file' => $this->upload(), 'reference_note' => 'Acme Payroll',
        ])->assertRedirect(route('toolkit.index').'#submissions')
            ->assertSessionHas('toolkit_mailto', fn ($href) => str_starts_with($href, 'mailto:security@helios-foods.example?subject='));

        $submission = ToolkitSubmission::sole();
        $this->assertSame('Acme Payroll', $submission->reference_note);
        $this->assertSame('filled.xlsx', $submission->original_filename);
        $this->assertNull($submission->emailed_to);
        $this->assertFileExists($submission->path());
        $this->assertStringStartsWith($this->storage.'/app/toolkit-submissions/', $submission->path());
        Mail::assertNothingSent();

        $log = AuditLog::where('action', 'toolkit.submitted')->sole();
        $this->assertSame(['code' => 'FRM-05', 'submission_id' => $submission->id, 'note' => 'Acme Payroll', 'emailed_to' => null], $log->metadata);

        $this->actingAs($this->supervisor)->get('/toolkit')->assertSee('Acme Payroll')->assertSee(__('ui.toolkit.stored'));
    }

    public function test_submission_is_emailed_through_graph_with_the_file_attached(): void
    {
        $this->graph();
        $item = $this->item('FRM-05');

        $this->actingAs($this->supervisor)->post("/toolkit/{$item->id}/submit", [
            'file' => $this->upload('Ερωτηματολόγιο.pdf', 'application/pdf'), 'reference_note' => 'Cloud Payroll Ltd',
        ])->assertSessionHasNoErrors()->assertSessionMissing('toolkit_mailto');

        $submission = ToolkitSubmission::sole();
        $this->assertSame('security@helios-foods.example', $submission->emailed_to);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/sendMail')
            && $r['message']['toRecipients'][0]['emailAddress']['address'] === 'security@helios-foods.example'
            && str_contains($r['message']['subject'], 'FRM-05')
            && $r['message']['replyTo'][0]['emailAddress']['address'] === 'supervisor@helios-foods.example'
            && str_contains($r['message']['body']['content'], 'Cloud Payroll Ltd')
            && $r['message']['attachments'][0]['name'] === 'Ερωτηματολόγιο.pdf'
            && $r['message']['attachments'][0]['contentType'] === 'application/pdf'
            && $r['message']['attachments'][0]['contentBytes'] === base64_encode(file_get_contents($submission->path())));
        $this->assertSame('security@helios-foods.example', AuditLog::where('action', 'toolkit.submitted')->sole()->metadata['emailed_to']);
    }

    public function test_failed_email_still_keeps_the_submission(): void
    {
        $this->graph(badSecret: true);

        $this->actingAs($this->supervisor)->post('/toolkit/'.$this->item('FRM-05')->id.'/submit', ['file' => $this->upload()])
            ->assertSessionHas('toolkit_mail_error')->assertSessionHas('toolkit_mailto');

        $this->assertNull(ToolkitSubmission::sole()->emailed_to);
        $this->assertTrue(AuditLog::where('action', 'toolkit.mail_failed')->exists());
    }

    public function test_only_forms_accept_allowed_files(): void
    {
        $form = $this->item('FRM-05');

        $this->actingAs($this->supervisor)->post("/toolkit/{$form->id}/submit", ['file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->supervisor)->post("/toolkit/{$form->id}/submit", ['file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->supervisor)->post("/toolkit/{$form->id}/submit", [])->assertSessionHasErrors('file');
        $this->actingAs($this->supervisor)->post('/toolkit/'.$this->item('PR-05')->id.'/submit', ['file' => $this->upload()])->assertNotFound();

        $this->assertSame(0, ToolkitSubmission::count());
    }

    public function test_submitted_files_are_visible_to_their_owner_and_to_hr_and_admins(): void
    {
        $this->actingAs($this->supervisor)->post('/toolkit/'.$this->item('FRM-05')->id.'/submit', ['file' => $this->upload(), 'reference_note' => 'Vendor A']);
        $submission = ToolkitSubmission::sole();
        $hr = $this->person('hr@helios-foods.example');
        $other = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($this->supervisor)->get("/toolkit/submissions/{$submission->id}/download")->assertOk()->assertDownload('filled.xlsx');
        $this->actingAs($other)->get("/toolkit/submissions/{$submission->id}/download")->assertForbidden();
        $this->actingAs($other)->get('/toolkit')->assertDontSee('Vendor A')->assertDontSee(__('ui.toolkit.all_submissions'));

        $this->actingAs($hr)->get('/toolkit')->assertSee(__('ui.toolkit.all_submissions'))->assertSee('Vendor A')->assertSee($this->supervisor->name);
        $this->actingAs($hr)->get("/toolkit/submissions/{$submission->id}/download")->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'toolkit.submission_downloaded')->count());
    }

    public function test_admin_manages_items_and_templates_in_the_browser(): void
    {
        $admin = $this->person('admin@helios-foods.example');
        $hr = $this->person('hr@helios-foods.example');

        $this->actingAs($admin)->get('/admin/content')->assertOk()->assertSee(__('admin.content.toolkit'))->assertSee('FRM-05');
        $this->actingAs($admin)->get('/admin/content/toolkit/create')->assertOk()->assertSee('name="template_file"', false);
        $this->actingAs($hr)->get('/admin/content/toolkit/create')->assertForbidden();

        $this->actingAs($admin)->post('/admin/content/toolkit', [
            'code' => 'FRM-10', 'type' => 'form', 'title' => ['el' => 'Έλεγχος νέου έργου', 'en' => 'New project checklist'],
            'when_to_use' => ['el' => 'Στην αρχή κάθε έργου.'], 'related_codes' => 'PR-05, FRM-05; FRM-10',
            'template_file' => UploadedFile::fake()->create('Checklist v3.docx', 30, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'is_active' => 1,
        ])->assertRedirect('/admin/content');

        $item = $this->item('FRM-10');
        $this->assertSame(['PR-05', 'FRM-05'], $item->related_codes);
        $this->assertSame('frm-10.docx', $item->file_path);
        $this->assertSame('Checklist v3.docx', $item->original_filename);
        $this->assertTrue($item->hasFile());
        $this->assertSame(['FRM-05', 'FRM-10', 'GD-01', 'PR-05'], ToolkitItem::ordered()->pluck('code')->all());
        $this->assertTrue(AuditLog::where('action', 'content.created')->get()->contains(fn ($l) => $l->metadata['type'] === 'toolkit' && $l->metadata['ref'] === 'FRM-10'));

        $this->actingAs($admin)->post('/admin/content/toolkit', ['code' => 'X', 'type' => 'form', 'title' => ['el' => 'X'], 'template_file' => UploadedFile::fake()->create('x.exe', 1)])
            ->assertSessionHasErrors('template_file');
        $this->actingAs($admin)->post('/admin/content/toolkit', ['code' => 'FRM-05', 'type' => 'memo', 'title' => ['el' => 'X']])
            ->assertSessionHasErrors(['code', 'type']);

        $this->actingAs($admin)->put("/admin/content/toolkit/{$item->id}", [
            'code' => 'FRM-10', 'type' => 'form', 'title' => ['el' => 'Έλεγχος νέου έργου'], 'remove_file' => 1, 'is_active' => 0,
        ])->assertRedirect();
        $item->refresh();
        $this->assertNull($item->file_path);
        $this->assertFalse($item->is_active);
        $this->actingAs($this->supervisor)->get('/toolkit')->assertDontSee('Έλεγχος νέου έργου');
    }

    public function test_packs_carry_the_toolkit_and_stay_optional(): void
    {
        $this->item('FRM-05')->update(['is_active' => false]);

        // Export: toolkit.yaml plus the template files.
        $zipPath = app(PackExporter::class)->export();
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $yaml = $zip->getFromName('demo/toolkit.yaml');
        $this->assertStringContainsString('FRM-05', $yaml);
        $this->assertStringContainsString('active: false', $yaml);
        $this->assertNotFalse($zip->getFromName('demo/toolkit/frm-05-supplier-questionnaire.xlsx'));
        $zip->close();

        // Re-installing keeps an item an admin switched off.
        $this->installDemo();
        $this->assertFalse($this->item('FRM-05')->is_active);

        // A pack without toolkit.yaml installs fine and leaves the toolkit alone.
        $dir = $this->storage.'/pack-without-toolkit';
        File::copyDirectory(base_path('packs/demo'), $dir);
        File::delete("$dir/toolkit.yaml");
        File::deleteDirectory("$dir/toolkit");
        $summary = app(PackLoader::class)->install($dir);
        $this->assertSame(0, $summary['toolkit']);
        $this->assertSame(3, ToolkitItem::count());

        // Mistakes are all reported together.
        File::put("$dir/toolkit.yaml", "- code: A-1\n  type: memo\n  title: A\n  file: toolkit/missing.docx\n  related: [B-2]\n- code: A-1\n  type: form\n  title: B\n");
        try {
            app(PackLoader::class)->read($dir);
            $this->fail('Expected PackException');
        } catch (PackException $e) {
            $text = implode("\n", $e->errors);
            $this->assertStringContainsString("'A-1' is listed more than once", $text);
            $this->assertStringContainsString("'type' must be one of form, procedure, guide", $text);
            $this->assertStringContainsString("file 'toolkit/missing.docx' was not found", $text);
            $this->assertStringContainsString("related code 'B-2' is not in toolkit.yaml", $text);
        }
    }

    public function test_import_command_finds_documents_by_their_code(): void
    {
        $dir = $this->storage.'/ΣΔΑΠ';
        foreach ([
            'Έντυπα/Ε Α5-1 Φόρμα-Αναφοράς-24ωρών.xlsx', 'Έντυπα/Ε Α5-10 ΔΕΛΤΙΟ ΕΛΕΓΧΟΥ ΑΣΦΑΛΕΙΑΣ ΕΡΓΟΥ.xlsx',
            'Έντυπα/E_A5-4 ΕΡΩΤΗΜΑΤΟΛΟΓΙΟ ΑΞΙΟΛΟΓΗΣΗΣ ΠΡΟΜΗΘΕΥΤΗ.xlsx', 'Έντυπα/~$E A5-4 ΕΡΩΤΗΜΑΤΟΛΟΓΙΟ.xlsx',
            'Έντυπα/Ε 9.3-1 ΠΡΑΚΤΙΚΑ ΑΝΑΣΚΟΠΗΣΗΣ ΔΙΟΙΚΗΣΗΣ.doc', 'Έντυπα/Ε 7.5-1 ΚΑΤΑΛΟΓΟΣ.lnk',
            'Διαδικασίες/Δ Α5-5 ΔΙΑΔΙΚΑΣΙΑ ΔΙΑΣΦΑΛΙΣΗΣ Α.Π. ΠΡΟΜΗΘΕΥΤΩΝ.docx', 'Διαδικασίες/Δ Α5-5 ΔΙΑΔΙΚΑΣΙΑ ΔΙΑΣΦΑΛΙΣΗΣ Α.Π. ΠΡΟΜΗΘΕΥΤΩΝ.pdf',
            'Διαδικασίες/Δ Α8-6 ΔΙΑΔΙΚΑΣΙΑ ΔΙΑΧΕΙΡΙΣΗΣ ΚΑΙ ΕΠΑΝΑΦΟΡΑΣ ΑΝΤΙΓΡΑΦΩΝ.docx', 'Διαδικασίες/Δ Α8-6 ΔΙΑΔΙΚΑΣΙΑ ΔΙΑΧΕΙΡΙΣΗΣ ΚΡΥΠΤΟΓΡΑΦΙΑΣ.docx',
        ] as $name) {
            File::ensureDirectoryExists(dirname("$dir/$name"));
            File::put("$dir/$name", "content of $name");
        }
        $yaml = <<<'YAML'
        - { code: "Ε Α5-1", type: form, title: Φόρμα αναφοράς 24 ωρών }
        - { code: "Ε Α5-10", type: form, title: Δελτίο ελέγχου ασφάλειας έργου, related: ["Δ Α5-5", PR-05] }
        - { code: "Ε Α5-4", type: form, title: Ερωτηματολόγιο αξιολόγησης προμηθευτή }
        - { code: "Ε 9.3-1", type: form, title: Πρακτικά ανασκόπησης διοίκησης }
        - { code: "Δ Α5-5", type: procedure, title: Διασφάλιση Α.Π. προμηθευτών, file: "Δ Α5-5*.pdf" }
        - { code: "Δ Α8-6", type: procedure, title: Αντίγραφα ασφαλείας, file: "*ΑΝΤΙΓΡΑΦΩΝ*" }
        - { code: "ΟΔ-01", type: guide, title: Οδηγός }
        YAML;
        File::put("$dir/toolkit.yaml", $yaml);

        $this->artisan('toolkit:import', ['path' => $dir, '--validate-only' => true])->assertSuccessful();
        $this->assertSame(3, ToolkitItem::count(), 'validate-only changes nothing');

        $this->artisan('toolkit:import', ['path' => $dir])
            ->expectsOutputToContain("'ΟΔ-01': no file starting with this code")
            ->assertSuccessful();

        $files = ToolkitItem::pluck('original_filename', 'code');
        $this->assertSame('Ε Α5-1 Φόρμα-Αναφοράς-24ωρών.xlsx', $files['Ε Α5-1']);
        $this->assertSame('Ε Α5-10 ΔΕΛΤΙΟ ΕΛΕΓΧΟΥ ΑΣΦΑΛΕΙΑΣ ΕΡΓΟΥ.xlsx', $files['Ε Α5-10']);
        $this->assertSame('E_A5-4 ΕΡΩΤΗΜΑΤΟΛΟΓΙΟ ΑΞΙΟΛΟΓΗΣΗΣ ΠΡΟΜΗΘΕΥΤΗ.xlsx', $files['Ε Α5-4']); // Latin E and underscore
        $this->assertSame('Ε 9.3-1 ΠΡΑΚΤΙΚΑ ΑΝΑΣΚΟΠΗΣΗΣ ΔΙΟΙΚΗΣΗΣ.doc', $files['Ε 9.3-1']);
        $this->assertSame('Δ Α5-5 ΔΙΑΔΙΚΑΣΙΑ ΔΙΑΣΦΑΛΙΣΗΣ Α.Π. ΠΡΟΜΗΘΕΥΤΩΝ.pdf', $files['Δ Α5-5']);
        $this->assertSame('Δ Α8-6 ΔΙΑΔΙΚΑΣΙΑ ΔΙΑΧΕΙΡΙΣΗΣ ΚΑΙ ΕΠΑΝΑΦΟΡΑΣ ΑΝΤΙΓΡΑΦΩΝ.docx', $files['Δ Α8-6']);
        $this->assertNull($files['ΟΔ-01']);
        $this->assertTrue($this->item('Ε Α5-1')->hasFile());
        $this->assertTrue(AuditLog::where('action', 'toolkit.imported')->exists());

        $this->actingAs($this->supervisor)->get('/toolkit/'.$this->item('Ε Α5-10')->id.'/download')
            ->assertDownload('e-a5-10-'.now()->timezone('Europe/Athens')->format('Y-m-d').'.xlsx');

        // Two files for one code: nothing is imported until the right one is chosen.
        File::put("$dir/toolkit.yaml", "- { code: \"Δ Α8-6\", type: procedure, title: X }\n- { code: \"Δ Α5-5\", type: procedure, title: Y }\n");
        $this->artisan('toolkit:import', ['path' => $dir])
            ->expectsOutputToContain("'Δ Α8-6' matches several files")
            ->expectsOutputToContain("'Δ Α5-5' matches several files")
            ->assertFailed();
        $this->assertSame('Αντίγραφα ασφαλείας', loc($this->item('Δ Α8-6')->title));
    }

    public function test_pack_finds_toolkit_documents_in_its_toolkit_folders(): void
    {
        $dir = $this->storage.'/packs/dryfo';
        File::copyDirectory(base_path('packs/demo'), $dir);
        foreach (['toolkit/Δ/Δ Α5-7 ΔΙΑΔΙΚΑΣΙΑ ΕΡΓΩΝ.docx', 'toolkit/Ε/Ε Α5-10 ΔΕΛΤΙΟ ΕΛΕΓΧΟΥ ΑΣΦΑΛΕΙΑΣ ΕΡΓΟΥ.xlsx', 'toolkit/Ε/Ε Α5-1 Φόρμα 24ωρών.xlsx'] as $name) {
            File::ensureDirectoryExists(dirname("$dir/$name"));
            File::put("$dir/$name", $name);
        }
        File::append("$dir/toolkit.yaml", "\n".implode("\n", [
            '- { code: "Δ Α5-7", type: procedure, title: Έργα, related: ["Ε Α5-10"] }',
            '- { code: "Ε Α5-10", type: form, title: Δελτίο έργου }',
            '- { code: "Ε Α5-1", type: form, title: Φόρμα 24 ωρών }',
        ])."\n");

        // pack:install finds the documents by code inside toolkit/ and its sub-folders.
        app(PackLoader::class)->install($dir);
        $this->assertSame('Δ Α5-7 ΔΙΑΔΙΚΑΣΙΑ ΕΡΓΩΝ.docx', $this->item('Δ Α5-7')->original_filename);
        $this->assertSame('Ε Α5-10 ΔΕΛΤΙΟ ΕΛΕΓΧΟΥ ΑΣΦΑΛΕΙΑΣ ΕΡΓΟΥ.xlsx', $this->item('Ε Α5-10')->original_filename);
        $this->assertSame('Ε Α5-1 Φόρμα 24ωρών.xlsx', $this->item('Ε Α5-1')->original_filename);
        $this->assertTrue($this->item('Ε Α5-10')->hasFile());
        $this->assertNull($this->item('PR-05')->file_path, 'an item with no matching document simply has no template');

        // toolkit:import on the same pack folder does the same, without reinstalling the pack.
        File::put("$dir/toolkit/Ε/Ε Α5-1 Φόρμα 24ωρών.xlsx", 'new version');
        $this->artisan('toolkit:import', ['path' => $dir])->expectsOutputToContain('toolkit')->assertSuccessful();
        $this->assertSame('new version', file_get_contents($this->item('Ε Α5-1')->filePath()));
    }

    public function test_import_command_defaults_to_the_installed_pack_folder(): void
    {
        $this->artisan('toolkit:import', ['--validate-only' => true])
            ->expectsOutputToContain('packs/demo')
            ->assertSuccessful();
    }
}
