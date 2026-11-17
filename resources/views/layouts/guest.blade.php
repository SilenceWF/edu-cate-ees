<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-header">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-6 sm:px-6">
        <div class="flex items-center justify-between text-white">
            @include('partials.logo')
            @include('partials.locale')
        </div>
        <main class="flex flex-1 items-center py-10">
            <div class="w-full">@yield('content')</div>
        </main>
    </div>
</body>
</html>
