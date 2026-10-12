<?php
$lang = app()->getLocale();
$json = file_exists(resource_path("js/locales/{$lang}.json"))
    ? file_get_contents(resource_path("js/locales/{$lang}.json"))
    : '{}';
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $lang) }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Stayflow') }}</title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏨</text></svg>">

    @routes
    @inertiaHead
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-gray-900">
    @inertia
</body>
</html>