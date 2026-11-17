<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AuditComplianceExport;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Services\Audit;
use App\Services\Roster;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends Controller
{
    public function index()
    {
        return view('admin.exports', [
            'campaign' => Campaign::current(),
            'groups' => TrainingGroup::orderBy('sort_order')->get(),
            'sessions' => TrainingSession::orderByDesc('session_date')->get(),
        ]);
    }

    /** Export 1: the full training compliance register (three sheets). */
    public function compliance(AuditComplianceExport $export)
    {
        $campaign = Campaign::current();
        Audit::log('export.compliance', null, ['campaign' => $campaign->name]);

        return $this->download($export->build($campaign), Str::slug(__('admin.exports.compliance_file').'-'.$campaign->name).'-'.now()->format('Ymd').'.xlsx');
    }

    /** Export 2: printable attendance sheet for a group or a live session. */
    public function attendance(Request $request)
    {
        $campaign = Campaign::current();
        $session = $request->filled('session') ? TrainingSession::with('group')->findOrFail($request->integer('session')) : null;
        $group = $session?->group ?? ($request->filled('group') ? TrainingGroup::findOrFail($request->integer('group')) : null);

        $rows = Roster::rows($campaign)->when($group, fn ($c) => $c->filter(fn ($r) => $r['user']->training_group_id === $group->id))->values();
        $attendance = $session ? $session->attendances()->get()->keyBy('user_id') : collect();
        Audit::log('export.attendance_sheet', null, ['group' => $group?->key, 'session_id' => $session?->id]);

        return Pdf::loadView('pdf.attendance-sheet', [
            'campaign' => $campaign, 'group' => $group, 'session' => $session, 'rows' => $rows,
            'attendance' => $attendance, 'logo' => BrandController::logoDataUri(),
        ])->setPaper('a4', 'landscape')->download(Str::slug(__('admin.exports.attendance_file').'-'.($session?->title ?? loc($group?->name) ?: 'all')).'.pdf');
    }

    /** The complete audit trail, with its hash chain. */
    public function audit()
    {
        Audit::log('export.audit_log');

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Audit log');
        $sheet->fromArray(['ID', __('admin.audit.when').' (UTC)', __('admin.audit.when').' ('.setting('timezone', 'Europe/Athens').')', __('admin.audit.actor'), __('admin.audit.target'), __('admin.audit.action'), __('admin.audit.details'), 'IP', 'User agent', 'Hash']);
        $n = 2;
        foreach (AuditLog::with(['actor', 'target'])->orderBy('id')->cursor() as $log) {
            $sheet->fromArray([
                $log->id, $log->created_at->format('Y-m-d H:i:s'), local_dt($log->created_at, 'Y-m-d H:i:s'), $log->actor?->name, $log->target?->name,
                $log->action, $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : '', $log->ip_address, $log->user_agent, $log->hash,
            ], null, 'A'.$n++);
        }
        AuditComplianceExport::styleHeader($sheet);

        return $this->download($book, 'audit-log-'.now()->format('Ymd-His').'.xlsx');
    }

    private function download(Spreadsheet $book, string $filename)
    {
        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
