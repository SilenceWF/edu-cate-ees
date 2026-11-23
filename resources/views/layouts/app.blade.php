<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen">
    <header class="bg-header text-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}">@include('partials.logo')</a>

            <nav class="order-3 flex w-full items-center gap-1 overflow-x-auto text-sm font-bold sm:order-none sm:ml-6 sm:w-auto">
                <a href="{{ route('home') }}" @class(['rounded-xl px-3 py-2 whitespace-nowrap', 'bg-white/15' => request()->routeIs('home', 'policies.*', 'modules.*', 'quiz.*')])>{{ __('ui.nav.my_training') }}</a>
                @if (auth()->user()->canAccessToolkit())
                    <a href="{{ route('toolkit.index') }}" @class(['rounded-xl px-3 py-2 whitespace-nowrap', 'bg-white/15' => request()->routeIs('toolkit.*')])>{{ __('ui.nav.toolkit') }}</a>
                @endif
                @if (auth()->user()->isStaffViewer())
                    <a href="{{ route('admin.dashboard') }}" @class(['rounded-xl px-3 py-2 whitespace-nowrap', 'bg-white/15' => request()->routeIs('admin.*')])>{{ __('ui.nav.admin') }}</a>
                @endif
            </nav>

            <div class="ml-auto flex items-center gap-2">
                @if (auth()->user()->canLaunchKiosk())
                    <a href="{{ route('kiosk.unlock') }}" class="btn btn-sm bg-brand text-on-brand">
                        {!! icon_svg('tablet-smartphone', 'size-4') !!}<span class="hidden 2xl:inline">{{ __('ui.nav.kiosk') }}</span><span class="2xl:hidden" title="{{ __('ui.nav.kiosk') }}">Kiosk</span>
                    </a>
                @endif
                @include('partials.locale')
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold opacity-80 hover:opacity-100" title="{{ __('ui.nav.logout') }}">
                        <span class="hidden max-w-40 truncate lg:inline">{{ auth()->user()->name }}</span>{!! icon_svg('log-out', 'size-5') !!}
                    </button>
                </form>
            </div>
        </div>
        @hasSection('subnav')
            <div class="border-t border-white/10">
                <div class="mx-auto max-w-6xl overflow-x-auto px-4 sm:px-6">@yield('subnav')</div>
            </div>
        @endif
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-sm text-muted sm:px-6">
        {{ setting('company.legal_name', setting('company.name')) }} · {{ loc(setting('company.frameworks')) }}
    </footer>
</body>
</html>
