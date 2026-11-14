<div class="space-y-4">
    <h2 class="text-xl font-semibold">{{ $company->name }}</h2>
    <p class="text-sm text-slate-600">Votre rôle : <strong>{{ $role->label() }}</strong></p>
    <dl class="grid grid-cols-2 gap-3 sm:max-w-md">
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Contacts</dt><dd class="text-2xl font-semibold">{{ $contacts }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Avec consentement valide</dt><dd class="text-2xl font-semibold">{{ $sendable }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Désinscrits</dt><dd class="text-2xl font-semibold">{{ $suppressed }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Solde de crédits</dt><dd class="text-2xl font-semibold"><a href="{{ route('credits.index') }}">{{ $balance }}</a></dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Groupes</dt><dd class="text-2xl font-semibold">{{ $groups }}</dd></div>
    </dl>
    <div class="rounded border border-dashed bg-white p-4 text-sm text-slate-600">
        Aucun envoi pour le moment : les campagnes et rapports seront ajoutés dans les prochaines étapes. Les chiffres ci-dessus sont les données réelles de votre entreprise.
    </div>
</div>
