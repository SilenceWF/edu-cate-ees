<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ setting('company.name', config('app.name')) }}</title>
@if ($favicon = app(\App\Support\Favicon::class)->urlOrLogo())<link rel="icon" href="{{ $favicon }}">@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('partials.brand')
