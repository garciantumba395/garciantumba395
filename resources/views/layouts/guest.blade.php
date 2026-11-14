<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
        <h1 class="mb-6 text-center text-2xl font-semibold">{{ config('app.name') }}</h1>
        <div class="rounded-xl bg-white p-6 shadow">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
