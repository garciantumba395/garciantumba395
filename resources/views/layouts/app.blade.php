<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="font-semibold">{{ config('app.name') }}</a>
            <nav class="flex flex-wrap items-center gap-4 text-sm">
                <a href="{{ route('dashboard') }}" class="hover:underline">Tableau de bord</a>
                <a href="{{ route('contacts.index') }}" class="hover:underline">Contacts</a>
                <a href="{{ route('groups.index') }}" class="hover:underline">Groupes</a>
                <a href="{{ route('suppressions.index') }}" class="hover:underline">Désinscriptions</a>
                <a href="{{ route('campaigns.index') }}" class="hover:underline">Campagnes</a>
                <a href="{{ route('messages.index') }}" class="hover:underline">Messages</a>
                <a href="{{ route('credits.index') }}" class="hover:underline">Crédits</a>
                @if (auth()->user()?->is_platform_admin)<a href="{{ route('admin.companies') }}" class="font-medium text-amber-700 hover:underline">Administration</a><a href="{{ route('admin.dashboard') }}" class="font-medium text-amber-700 hover:underline">Plateforme</a>@endif
                <a href="{{ route('companies.members') }}" class="hover:underline">Membres</a>
                <a href="{{ route('companies.settings') }}" class="hover:underline">Paramètres</a>
                <livewire:companies.switcher />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-red-600 hover:underline">Déconnexion</button>
                </form>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
