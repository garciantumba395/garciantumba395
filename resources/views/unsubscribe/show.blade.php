<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Se désabonner — {{ $company->senderName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
    <div class="rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-semibold">Se désabonner</h1>
        @if ($already)
            <p class="mt-3 rounded bg-green-50 p-3 text-sm text-green-800" role="status">Ce numéro est déjà désabonné. Aucun message publicitaire ne lui sera plus envoyé.</p>
        @endif
        <p class="mt-3 text-sm">Vous ne souhaitez plus recevoir de messages publicitaires de <strong>{{ $company->senderName() }}</strong> ? Confirmez ci-dessous. C'est <strong>gratuit</strong>, sans justification.</p>
        <form method="POST" action="{{ route('unsubscribe.confirm', ['code' => $code]) }}" class="mt-4">
            @csrf
            <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Me désabonner</button>
        </form>
        <section class="mt-6 border-t pt-4 text-xs text-slate-600" aria-label="Identité de l'expéditeur">
            <h2 class="mb-1 font-medium text-slate-700">Expéditeur du message</h2>
            <dl class="space-y-0.5">
                <div><dt class="inline font-medium">Nom : </dt><dd class="inline">{{ $company->senderName() }}</dd></div>
                @if ($company->legal_address)<div><dt class="inline font-medium">Adresse : </dt><dd class="inline">{{ $company->legal_address }}</dd></div>@endif
                @if ($company->legal_email)<div><dt class="inline font-medium">E-mail : </dt><dd class="inline">{{ $company->legal_email }}</dd></div>@endif
                @if ($company->legal_phone)<div><dt class="inline font-medium">Téléphone : </dt><dd class="inline">{{ $company->legal_phone }}</dd></div>@endif
                @if ($company->legal_rccm)<div><dt class="inline font-medium">RCCM : </dt><dd class="inline">{{ $company->legal_rccm }}</dd></div>@endif
                @if ($company->legal_tax_id)<div><dt class="inline font-medium">Numéro d'impôt : </dt><dd class="inline">{{ $company->legal_tax_id }}</dd></div>@endif
                @if ($company->legal_act)<div><dt class="inline font-medium">Acte autorisant l'activité : </dt><dd class="inline">{{ $company->legal_act }}</dd></div>@endif
            </dl>
        </section>
    </div>
</main>
</body>
</html>
