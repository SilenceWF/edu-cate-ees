<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalController;
use App\Mail\TrainingReminderMail;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Track;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\Audit;
use App\Services\Progress;
use App\Services\RosterImporter;
use App\Support\MailStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
{
    public function show(User $user)
    {
        return view('admin.employee', ['user' => $user->load('group', 'department'), 'p' => Progress::forUser($user), 'campaign' => Campaign::current()]);
    }

    public function create()
    {
        return $this->form(new User(['role' => 'employee', 'is_active' => true]));
    }

    public function edit(User $user)
    {
        return $this->form($user);
    }

    public function store(Request $request, RosterImporter $importer)
    {
        $user = new User(['role' => 'employee']);
        $this->save($request, $user, $importer);

        return redirect()->route('admin.employees.show', $user)->with('status', __('admin.employees.created'));
    }

    public function update(Request $request, User $user, RosterImporter $importer)
    {
        $this->save($request, $user, $importer);

        return redirect()->route('admin.employees.show', $user)->with('status', __('admin.employees.updated'));
    }

    public function remind(Request $request, User $user)
    {
        abort_unless($user->email, 422);
        abort_unless(app(MailStatus::class)->canSend(), 409);

        try {
            Mail::to($user->email, $user->name)->send(new TrainingReminderMail($user));
        } catch (Throwable $e) {
            report($e);
            Audit::log('reminder.failed', $user, ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return back()->withErrors(['mail' => __('admin.reminder.failed', ['name' => $user->name]).' '.$e->getMessage()]);
        }
        Audit::log('reminder.sent', $user, ['to' => $user->email]);

        return back()->with('status', __('admin.reminder.sent', ['name' => $user->name]));
    }

    public function certificate(User $user)
    {
        $p = Progress::forUser($user);
        abort_unless($p['status'] === 'complete', 404);
        Audit::log('certificate.downloaded', $user, ['by_staff' => true]);

        return PortalController::certificatePdf($user, $p)->download(Str::slug(__('ui.certificate.filename').'-'.$user->name).'.pdf');
    }

    private function form(User $user)
    {
        return view('admin.employee-form', [
            'user' => $user,
            'groups' => TrainingGroup::orderBy('sort_order')->get(),
            'departments' => Department::orderBy('name')->get(),
            'tracks' => Track::orderBy('sort_order')->get(),
        ]);
    }

    private function save(Request $request, User $user, RosterImporter $importer): void
    {
        $isAdmin = $request->user()->hasRole('admin');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'employee_code' => ['nullable', 'string', 'max:32', Rule::unique('users')->ignore($user->id)],
            'training_group_id' => 'required|exists:training_groups,id',
            'department_id' => 'nullable|exists:departments,id',
            'new_department' => 'nullable|string|max:255',
            'track_id' => 'nullable|exists:tracks,id',
            'role' => ['nullable', Rule::in(User::ROLES)],
            'password' => 'nullable|string|min:8',
            'is_active' => 'nullable|boolean',
            'can_access_toolkit' => 'nullable|boolean',
        ]);

        if (filled($data['new_department'] ?? null)) {
            $data['department_id'] = Department::firstOrCreate(['name' => trim($data['new_department'])])->id;
        }

        $before = $user->only(['name', 'email', 'role', 'can_access_toolkit', 'training_group_id', 'department_id', 'track_id', 'is_active']);
        $user->fill([
            'name' => $data['name'],
            'email' => filled($data['email'] ?? null) ? Str::lower($data['email']) : null,
            'employee_code' => $data['employee_code'] ?? null,
            'training_group_id' => $data['training_group_id'],
            'department_id' => $data['department_id'] ?? null,
            'track_id' => $data['track_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        // Only admins may grant roles, toolkit access or set passwords; HR maintains the roster itself.
        if ($isAdmin) {
            $user->role = $data['role'] ?? $user->role;
            if ($request->has('can_access_toolkit')) {
                $user->can_access_toolkit = $request->boolean('can_access_toolkit');
            }
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
        }
        $user->save();

        $user->load('group.track', 'trackOverride');
        if (! $user->employee_code && $user->effectiveTrack()?->kiosk_enabled) {
            $user->update(['employee_code' => $importer->newEmployeeCode()]);
        }

        Audit::log($user->wasRecentlyCreated ? 'user.created' : 'user.updated', $user, [
            'before' => $user->wasRecentlyCreated ? null : $before,
            'after' => $user->only(['name', 'email', 'role', 'can_access_toolkit', 'training_group_id', 'department_id', 'track_id', 'is_active']),
        ]);
    }
}
