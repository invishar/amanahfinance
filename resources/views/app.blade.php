<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbf6f0">
    <meta name="description" content="Asisten keuangan keluarga yang ngerti obrolan sehari-hari">

    <title inertia>{{ config('app.name') }}</title>

    {{-- Di aplikasi Next font ini di-self-host oleh next/font. Di sini dimuat
         dari Google Fonts; nama family-nya dipetakan ke --font-sora /
         --font-plus-jakarta-sans di resources/css/app.css. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&family=Sora:wght@500;600;700&display=swap">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
