<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbf6f0">
    <meta name="description" content="Asisten keuangan keluarga yang ngerti obrolan sehari-hari">

    {{-- Dengan SSR hidup, isi <head> (termasuk <title> per halaman dari
         komponen <Head> Inertia) datang dari hasil render Node dan menggantikan
         seluruh slot ini. Slot dipakai hanya saat SSR mati atau gagal — kalau
         judul statis ini ditulis di luar komponen, ia akan berdampingan dengan
         judul hasil SSR dan browser memakai yang pertama (judul per halaman
         jadi percuma). --}}
    <x-inertia::head>
        <title inertia>{{ config('app.name') }}</title>
    </x-inertia::head>

    {{-- Di aplikasi Next font ini di-self-host oleh next/font. Di sini dimuat
         dari Google Fonts; nama family-nya dipetakan ke --font-sora /
         --font-plus-jakarta-sans di resources/css/app.css. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&family=Sora:wght@500;600;700&display=swap">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    @inertia
</body>
</html>
