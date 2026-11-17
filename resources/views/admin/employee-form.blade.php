@extends('layouts.app', ['title' => $user->exists ? __('ui.action.edit') : __('admin.employees.add')])
@include('admin._nav')

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ $user->exists ? route('admin.employees.show', $user) : route('admin.dashboard') }}" class="inline-flex items-center gap-2 font-bold text-muted hover:text-ink">{!! icon_svg('arrow-left', 'size-5') !!}{{ __('ui.action.back') }}</a>
    <h1 class="mt-5 text-4xl">{{ $user->exists ? $user->name : __('admin.employees.add_title') }}</h1>
    @unless ($user->exists)<p class="mt-3 text-lg text-muted">{{ __('admin.employees.add_lead') }}</p>@endunless

    <form method="POST" action="{{ $user->exists ? route('admin.employees.update', $user) : route('admin.employees.store') }}" class="card mt-6 grid gap-5 sm:grid-cols-2">
        @csrf @if ($user->exists) @method('PUT') @endif
        <div class="sm:col-span-2"><label class="label" for="name">{{ __('admin.cols.name') }} *</label><input class="field" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label class="label" for="email">Email</label><input class="field" id="email" type="email" name="email" value="{{ old('email', $user->email) }}"><p class="mt-1 text-sm text-muted">{{ __('admin.employees.email_hint') }}</p></div>
        <div><label class="label" for="employee_code">{{ __('admin.cols.employee_code') }}</label><input class="field" id="employee_code" name="employee_code" value="{{ old('employee_code', $user->employee_code) }}"><p class="mt-1 text-sm text-muted">{{ __('admin.employees.code_hint') }}</p></div>
        <div><label class="label" for="group">{{ __('admin.cols.group') }} *</label><select class="field" id="group" name="training_group_id" required>
            @foreach ($groups as $g)<option value="{{ $g->id }}" @selected(old('training_group_id', $user->training_group_id) == $g->id)>{{ loc($g->name) }}</option>@endforeach</select></div>
        <div><label class="label" for="track">{{ __('admin.cols.track') }}</label><select class="field" id="track" name="track_id"><option value="">{{ __('admin.employees.track_from_group') }}</option>
            @foreach ($tracks as $t)<option value="{{ $t->id }}" @selected(old('track_id', $user->track_id) == $t->id)>{{ loc($t->name) }}</option>@endforeach</select></div>
        <div><label class="label" for="department">{{ __('admin.cols.department') }}</label><select class="field" id="department" name="department_id"><option value="">—</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $user->department_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div><label class="label" for="new_department">{{ __('admin.employees.new_department') }}</label><input class="field" id="new_department" name="new_department" value="{{ old('new_department') }}"></div>
        @if (auth()->user()->hasRole('admin'))
            <div><label class="label" for="role">{{ __('admin.cols.role') }}</label><select class="field" id="role" name="role">
                @foreach (\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected(old('role', $user->role) === $r)>{{ __('admin.roles.'.$r) }}</option>@endforeach</select></div>
            @if (config('portal.local_auth'))
                <div><label class="label" for="password">{{ __('admin.employees.password') }}</label><input class="field" id="password" type="password" name="password" autocomplete="new-password"><p class="mt-1 text-sm text-muted">{{ __('admin.employees.password_hint') }}</p></div>
            @endif
        @endif
        <label class="flex items-center gap-3 font-bold sm:col-span-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="size-6 accent-[var(--brand)]" @checked(old('is_active', $user->is_active ?? true))>{{ __('admin.employees.active') }}</label>
        <div class="sm:col-span-2"><button class="btn-primary">{!! icon_svg('save', 'size-5') !!}{{ __('ui.action.save') }}</button></div>
    </form>
</div>
@endsection
