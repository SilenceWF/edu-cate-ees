<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Services\Roster;
use App\Support\MailStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $campaign = Campaign::find($request->integer('campaign')) ?? Campaign::current();
        $rows = Roster::rows($campaign);

        $needPolicies = $rows->filter(fn ($r) => $r['p']['required_policies']->isNotEmpty());
        $kpis = [
            'total' => $rows->count(),
            'policy_rate' => Roster::percent($needPolicies->filter(fn ($r) => $r['p']['policies_done'])->count(), $needPolicies->count()),
            'completion_rate' => Roster::percent($rows->where('p.status', 'complete')->count(), $rows->count()),
            'pending' => $rows->where('p.status', '!=', 'complete')->count(),
            'sessions' => TrainingSession::where('campaign_id', $campaign->id)->count(),
        ];

        $groups = TrainingGroup::with('track')->orderBy('sort_order')->get()->map(function (TrainingGroup $g) use ($rows) {
            $members = $rows->filter(fn ($r) => $r['user']->training_group_id === $g->id);

            return [
                'group' => $g,
                'total' => $members->count(),
                'complete' => $members->where('p.status', 'complete')->count(),
                'in_progress' => $members->where('p.status', 'in_progress')->count(),
                'percent' => Roster::percent($members->where('p.status', 'complete')->count(), $members->count()),
                'departments' => $members->pluck('user.department')->filter()->unique('id')->values(),
            ];
        });

        $filters = $request->only(['group', 'department', 'location', 'status', 'q']);
        $needle = Str::lower((string) ($filters['q'] ?? ''));
        $table = $rows->filter(fn ($r) => (empty($filters['group']) || $r['user']->training_group_id == $filters['group'])
            && (empty($filters['department']) || $r['user']->department_id == $filters['department'])
            && (empty($filters['location']) || $r['user']->department?->location === $filters['location'])
            && (empty($filters['status']) || $r['p']['status'] === $filters['status'])
            && ($needle === '' || Str::contains(Str::lower($r['user']->name.' '.$r['user']->email), $needle)))->values();

        return view('admin.dashboard', [
            'campaign' => $campaign,
            'canSendMail' => app(MailStatus::class)->canSend(),
            'campaigns' => Campaign::orderByDesc('id')->get(),
            'kpis' => $kpis,
            'groups' => $groups,
            'rows' => $table,
            'filters' => $filters,
            'allGroups' => TrainingGroup::orderBy('sort_order')->get(),
            'departments' => Department::orderBy('name')->get(),
            'locations' => Department::whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
        ]);
    }
}
