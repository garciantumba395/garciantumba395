<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Désabonnement enregistré — {{ $company->senderName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
    <div class="rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-semibold">Désabonnement enregistré</h1>
        <p class="mt-3 rounded bg-green-50 p-3 text-sm text-green-800" role="status">Votre demande est enregistrée. Vous ne recevrez plus de messages publicitaires de <strong>{{ $company->senderName() }}</strong>.</p>
        <p class="mt-3 text-xs text-slate-500">Cette page vaut accusé de réception de votre demande.</p>
    </div>
</main>
</body>
</html>
