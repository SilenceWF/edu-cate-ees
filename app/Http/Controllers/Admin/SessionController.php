<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\SessionAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    public function index()
    {
        return view('admin.sessions', [
            'sessions' => TrainingSession::with('group')->withCount('attendances')->orderByDesc('session_date')->get(),
            'groups' => TrainingGroup::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'training_group_id' => 'nullable|exists:training_groups,id',
            'session_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'trainer_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
        $session = TrainingSession::create($data + ['campaign_id' => Campaign::current()->id]);
        Audit::log('session.created', null, ['session_id' => $session->id, 'title' => $session->title]);

        return redirect()->route('admin.sessions.show', $session)->with('status', __('admin.sessions.created'));
    }

    public function show(TrainingSession $session)
    {
        $people = User::where('is_active', true)->with('department')
            ->when($session->training_group_id, fn ($q) => $q->where('training_group_id', $session->training_group_id))
            ->orderBy('name')->get();

        return view('admin.session', [
            'session' => $session->load('group'),
            'people' => $people,
            'attendance' => $session->attendances()->get()->keyBy('user_id'),
        ]);
    }

    public function attendance(Request $request, TrainingSession $session)
    {
        $data = $request->validate([
            'attendance' => 'array',
            'attendance.*' => ['nullable', Rule::in(TrainingSession::ATTENDANCE_TYPES)],
        ]);
        $existing = $session->attendances()->get()->keyBy('user_id');

        foreach ($data['attendance'] ?? [] as $userId => $type) {
            $current = $existing->get($userId);
            if (! $type) {
                if ($current) {
                    $current->delete();
                    Audit::log('attendance.removed', (int) $userId, ['session_id' => $session->id]);
                }

                continue;
            }
            if (! $current || $current->attendance_type !== $type) {
                SessionAttendance::updateOrCreate(
                    ['training_session_id' => $session->id, 'user_id' => $userId],
                    ['attendance_type' => $type, 'recorded_at' => now()],
                );
                Audit::log('attendance.recorded', (int) $userId, ['session_id' => $session->id, 'type' => $type]);
            }
        }

        return back()->with('status', __('admin.sessions.saved'));
    }

    public function destroy(TrainingSession $session)
    {
        Audit::log('session.deleted', null, ['session_id' => $session->id, 'title' => $session->title, 'attendances' => $session->attendances()->count()]);
        $session->delete();

        return redirect()->route('admin.sessions.index')->with('status', __('admin.sessions.deleted'));
    }
}
