<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen select-none">
    @yield('content')
</body>
</html>
