<?php

namespace App\Exports;

use App\Models\Campaign;
use App\Models\PolicyAcknowledgment;
use App\Models\TrainingGroup;
use App\Services\Roster;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The training compliance register handed to auditors: one workbook, three sheets
 * (summary per group, status per person, every individual policy acknowledgment).
 */
class AuditComplianceExport
{
    public function build(Campaign $campaign): Spreadsheet
    {
        $rows = Roster::rows($campaign);
        $book = new Spreadsheet;
        $book->getProperties()->setTitle(__('admin.exports.compliance_title'))->setCompany((string) setting('company.legal_name'));

        $this->summary($book->getActiveSheet(), $campaign, $rows);
        $this->people($book->createSheet(), $rows);
        $this->acknowledgments($book->createSheet(), $campaign);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function summary(Worksheet $sheet, Campaign $campaign, $rows): void
    {
        $t = fn (string $k) => __('admin.exports.cols.'.$k);
        $sheet->setTitle(mb_substr(__('admin.exports.sheet_summary'), 0, 31));
        $sheet->fromArray([$t('group'), $t('track'), $t('staff'), $t('policies_signed'), $t('policies_pct'), $t('quiz_passed'), $t('quiz_pct'), $t('overall_pct')]);

        $n = 2;
        $line = function (string $label, string $track, $members) use ($sheet, &$n) {
            $need = $members->filter(fn ($r) => $r['p']['required_policies']->isNotEmpty());
            $signed = $need->filter(fn ($r) => $r['p']['policies_done'])->count();
            $passed = $members->filter(fn ($r) => $r['p']['quiz_passed'])->count();
            $complete = $members->where('p.status', 'complete')->count();
            $sheet->fromArray([
                $label, $track, $members->count(), $signed, $need->count() ? round($signed / $need->count(), 4) : null,
                $passed, $members->count() ? round($passed / $members->count(), 4) : null,
                $members->count() ? round($complete / $members->count(), 4) : null,
            ], null, 'A'.$n++, true);
        };

        foreach (TrainingGroup::with('track')->orderBy('sort_order')->get() as $group) {
            $line(loc($group->name), loc($group->track->name), $rows->filter(fn ($r) => $r['user']->training_group_id === $group->id));
        }
        $line(__('admin.exports.total'), '', $rows);

        $sheet->getStyle('E2:E'.($n - 1))->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle('G2:H'.($n - 1))->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle('A'.($n - 1).':H'.($n - 1))->getFont()->setBold(true);
        $sheet->setCellValue('A'.($n + 1), __('admin.exports.generated', [
            'company' => setting('company.legal_name', setting('company.name')), 'campaign' => $campaign->name,
            'date' => local_dt(now()), 'frameworks' => loc(setting('company.frameworks')),
        ]));
        self::styleHeader($sheet);
    }

    private function people(Worksheet $sheet, $rows): void
    {
        $t = fn (string $k) => __('admin.exports.cols.'.$k);
        $sheet->setTitle(mb_substr(__('admin.exports.sheet_people'), 0, 31));
        $sheet->fromArray([$t('group'), $t('department'), $t('name'), 'Email', $t('employee_code'), $t('track'), $t('policies_date'), $t('policies_ip'),
            $t('modules_date'), $t('best_score'), $t('passed'), $t('passed_date'), $t('supervised'), $t('live_sessions'), $t('status')]);

        $n = 2;
        foreach ($rows as $r) {
            [$u, $p] = [$r['user'], $r['p']];
            $sheet->fromArray([
                loc($u->group?->name), $u->department?->name, $u->name, $u->email, $u->employee_code, loc($p['track']?->name),
                $p['required_policies']->isEmpty() ? __('admin.exports.not_required') : ($p['policies_signed_at'] ? local_dt($p['policies_signed_at'], 'Y-m-d H:i:s') : ''),
                $p['policies_ip'],
                $p['modules_done_at'] ? local_dt($p['modules_done_at'], 'Y-m-d H:i:s') : '',
                $p['best_attempt'] ? round($p['best_attempt']->percentage / 100, 4) : null,
                $p['quiz_passed'] ? __('admin.yes') : __('admin.no'),
                $p['passed_attempt'] ? local_dt($p['passed_attempt']->completed_at, 'Y-m-d H:i:s') : '',
                $p['supervised'] ? __('admin.yes') : '',
                $p['attendance']->map(fn ($a) => __('admin.attendance.'.$a->attendance_type))->implode(', '),
                __('admin.status.'.$p['status']),
            ], null, 'A'.$n++, true);
        }
        $sheet->getStyle('J2:J'.max(2, $n - 1))->getNumberFormat()->setFormatCode('0%');
        self::styleHeader($sheet);
    }

    private function acknowledgments(Worksheet $sheet, Campaign $campaign): void
    {
        $t = fn (string $k) => __('admin.exports.cols.'.$k);
        $sheet->setTitle(mb_substr(__('admin.exports.sheet_acks'), 0, 31));
        $sheet->fromArray([$t('name'), 'Email', $t('department'), $t('policy_code'), $t('policy_title'), $t('version'),
            $t('ack_date').' ('.setting('timezone', 'Europe/Athens').')', $t('ack_date').' (UTC)', 'IP', 'User agent', $t('supervisor')]);

        $n = 2;
        $acks = PolicyAcknowledgment::where('campaign_id', $campaign->id)->with(['user.department', 'policy', 'supervisor'])->orderBy('acknowledged_at')->cursor();
        foreach ($acks as $a) {
            $sheet->fromArray([
                $a->user?->name, $a->user?->email, $a->user?->department?->name, $a->policy?->code, loc($a->policy?->title), $a->policy_version,
                local_dt($a->acknowledged_at, 'Y-m-d H:i:s'), $a->acknowledged_at->format('Y-m-d H:i:s'), $a->ip_address, $a->user_agent, $a->supervisor?->name,
            ], null, 'A'.$n++, true);
        }
        self::styleHeader($sheet);
    }

    public static function styleHeader(Worksheet $sheet): void
    {
        $last = $sheet->getHighestColumn();
        $header = $sheet->getStyle('A1:'.$last.'1');
        $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(ltrim((string) setting('branding.colors.header', '#0F172A'), '#'));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$last.'1');
        foreach ($sheet->getColumnIterator('A', $last) as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
    }
}
