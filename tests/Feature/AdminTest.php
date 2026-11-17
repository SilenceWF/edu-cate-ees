<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\Audit;
use App\Services\RosterImporter;
use App\Services\Training;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Tests\TestCase;

class AdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->installDemo();
    }

    public function test_role_access(): void
    {
        $admin = $this->person('admin@helios-foods.example');
        $hr = $this->person('hr@helios-foods.example');

        foreach (['/admin', '/admin/import', '/admin/sessions', '/admin/content', '/admin/exports', '/admin/audit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($hr)->get($url)->assertOk();
            $this->actingAs($this->employee())->get($url)->assertForbidden();
        }
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($hr)->get('/admin/settings')->assertForbidden();
        $this->actingAs($hr)->get('/admin/content/policies/1/edit')->assertForbidden();
    }

    public function test_hr_can_add_a_worker_but_cannot_grant_a_role(): void
    {
        $hr = $this->person('hr@helios-foods.example');
        $group = DB::table('training_groups')->where('key', 'group-5')->value('id');

        $this->actingAs($hr)->post('/admin/employees', [
            'name' => 'Νέος Εργάτης', 'training_group_id' => $group, 'new_department' => 'Packaging - North Plant',
            'role' => 'admin', 'is_active' => 1,
        ])->assertRedirect();

        $worker = User::where('name', 'Νέος Εργάτης')->first();
        $this->assertSame('employee', $worker->role);
        $this->assertNotNull($worker->employee_code, 'shop-floor workers get a kiosk code automatically');
        $this->assertSame('Packaging - North Plant', $worker->department->name);
    }

    public function test_excel_import_reads_fill_colours_maps_tracks_and_keeps_progress(): void
    {
        $existing = $this->employee();
        app(Training::class)->completeModule($existing, $existing->group->track->modules->first());

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['Group', 'Τμήμα', 'Ονοματεπώνυμο', 'Τύπος', 'Email'],
            ['Group 5 - Παραγωγή', 'Packaging - North Plant', 'Γιάννης Δοκιμάκης', 'Βασική εκπαίδευση', null],
            ['Group 6 - Πληροφορική', 'IT - Head Office', 'Ελένη Δοκιμή', 'Εκπαίδευση Διαχειριστών', 'eleni@helios-foods.example'],
            ['Group 1 - Διοίκηση', 'Finance - Head Office', $existing->name, 'Εκτεταμένη', $existing->email],
        ]);
        $sheet->getStyle('B2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ED4799');
        $path = storage_path('framework/testing/plan.xlsx');
        @mkdir(dirname($path), 0777, true);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        $admin = $this->person('admin@helios-foods.example');
        $this->actingAs($admin)->post('/admin/import/preview', ['file' => new UploadedFile($path, 'plan.xlsx', null, null, true)])
            ->assertOk()->assertSee('Γιάννης Δοκιμάκης');
        $this->actingAs($admin)->post('/admin/import/apply')->assertRedirect('/admin');

        $this->assertSame('#ED4799', Department::where('name', 'Packaging - North Plant')->value('hex_color'));
        $this->assertSame('shopfloor_basic', User::where('name', 'Γιάννης Δοκιμάκης')->first()->effectiveTrack()->key);
        $this->assertSame('it_specialized', User::where('email', 'eleni@helios-foods.example')->first()->effectiveTrack()->key);

        $existing->refresh();
        $this->assertSame('group-1', $existing->group->key, 'existing person moved to the new group');
        $this->assertSame(1, $existing->completions()->count(), 'progress kept');
        @unlink($path);
    }

    public function test_exports_download(): void
    {
        $admin = $this->person('admin@helios-foods.example');
        $this->actingAs($admin)->get('/admin/exports/compliance')->assertOk()->assertDownload();
        $this->actingAs($admin)->get('/admin/exports/audit')->assertOk()->assertDownload();
        $pdf = $this->actingAs($admin)->get('/admin/exports/attendance');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_audit_log_is_append_only_and_tamper_evident(): void
    {
        Audit::log('test.event', null, ['x' => 1]);
        $this->assertNull(Audit::firstBrokenLink());

        $log = AuditLog::latest('id')->first();
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'changed']);
    }

    public function test_database_rejects_audit_log_changes(): void
    {
        Audit::log('test.event');
        $this->expectException(QueryException::class);
        DB::table('audit_logs')->delete();
    }

    public function test_kiosk_pin_can_be_changed(): void
    {
        $this->actingAs($this->person('admin@helios-foods.example'))
            ->put('/admin/settings/kiosk-pin', ['pin' => '13579', 'pin_confirmation' => '13579'])->assertSessionHasNoErrors();
        $this->actingAs($this->person('supervisor@helios-foods.example'))->post('/kiosk/unlock', ['pin' => '13579'])->assertRedirect('/kiosk');
    }

    public function test_importer_generates_unique_codes(): void
    {
        $codes = collect(range(1, 20))->map(fn () => app(RosterImporter::class)->newEmployeeCode());
        $this->assertCount(20, $codes->unique());
    }
}
