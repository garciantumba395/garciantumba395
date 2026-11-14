<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0b1f4a">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/icons/favicon-64.png" type="image/png" sizes="64x64">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
        <h1 class="mb-6 flex justify-center"><img src="/brand/logo-vertical.png" alt="{{ config('app.name') }} — Vos messages, plus proches de vos clients." width="220" height="171" class="h-auto w-[220px] max-w-full"></h1>
        <div class="rounded-xl bg-white p-6 shadow">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
