@section('subnav')
<nav class="flex items-center gap-1 py-2 text-sm font-bold whitespace-nowrap">
    @foreach ([
        ['admin.dashboard', 'layout-dashboard', 'dashboard', ['admin.dashboard', 'admin.employees.*']],
        ['admin.import', 'file-spreadsheet', 'import', ['admin.import', 'admin.import.*']],
        ['admin.sessions.index', 'presentation', 'sessions', ['admin.sessions.*']],
        ['admin.content', 'book-open', 'content', ['admin.content', 'admin.content.*']],
        ['admin.exports', 'download', 'exports', ['admin.exports']],
        ['admin.audit', 'scroll-text', 'audit', ['admin.audit']],
    ] as [$route, $icon, $key, $active])
        <a href="{{ route($route) }}" @class(['flex items-center gap-2 rounded-xl px-3 py-2', 'bg-white/15' => request()->routeIs(...$active), 'opacity-70 hover:opacity-100' => ! request()->routeIs(...$active)])>{!! icon_svg($icon, 'size-4') !!}{{ __('admin.nav.'.$key) }}</a>
    @endforeach
    @if (auth()->user()->hasRole('admin'))
        <a href="{{ route('admin.settings') }}" @class(['flex items-center gap-2 rounded-xl px-3 py-2', 'bg-white/15' => request()->routeIs('admin.settings'), 'opacity-70 hover:opacity-100' => ! request()->routeIs('admin.settings')])>{!! icon_svg('settings', 'size-4') !!}{{ __('admin.nav.settings') }}</a>
    @endif
</nav>
@endsection
