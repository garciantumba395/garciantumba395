<div class="space-y-6">
    <h2 class="text-xl font-semibold">Administration — tableau de bord</h2>
    <p class="text-sm text-slate-600">Chiffres réels de la plateforme (agrégats uniquement : aucun contact, numéro ni texte de message d'entreprise n'est accessible ici).</p>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Entreprises</dt><dd class="text-2xl font-semibold" id="stat-companies">{{ $companies }}</dd><dd class="text-xs text-slate-500">dont {{ $companiesActive }} active(s)</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Utilisateurs</dt><dd class="text-2xl font-semibold" id="stat-users">{{ $users }}</dd><dd class="text-xs text-slate-500">dont {{ $usersDisabled }} désactivé(s)</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Crédits en circulation (somme des soldes)</dt><dd class="text-2xl font-semibold" id="stat-credits">{{ \App\Domain\Credits\CreditUnits::format($creditUnits) }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Messages réels (journal)</dt><dd class="text-2xl font-semibold" id="stat-real">{{ $messagesReal }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Messages simulés (journal)</dt><dd class="text-2xl font-semibold" id="stat-simulated">{{ $messagesSimulated }}</dd><dd class="text-xs text-slate-500">Jamais additionnés aux réels.</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Campagnes actives</dt><dd class="text-2xl font-semibold" id="stat-campaigns">{{ $campaignsActive }}</dd><dd class="text-xs text-slate-500">programmées, en préparation ou en envoi ; {{ $campaignsPaused }} en pause</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Jobs en échec (file)</dt><dd class="text-2xl font-semibold" id="stat-failed">{{ $failedJobs }}</dd></div>
    </dl>

    <nav class="flex flex-wrap gap-4 text-sm" aria-label="Administration">
        <a class="underline" href="{{ route('admin.companies') }}">Entreprises et crédits</a>
        <a class="underline" href="{{ route('admin.users') }}">Utilisateurs</a>
        <a class="underline" href="{{ route('admin.rates') }}">Tarifs</a>
        <a class="underline" href="{{ route('admin.audit') }}">Journal d'audit</a>
    </nav>
</div>
