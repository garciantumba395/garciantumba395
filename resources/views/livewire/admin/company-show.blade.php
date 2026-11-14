<div class="space-y-6">
    <p class="text-sm"><a class="underline" href="{{ route('admin.companies') }}">← Entreprises</a></p>
    <h2 class="text-xl font-semibold">{{ $company->name }} @unless ($company->is_active)<span class="rounded bg-red-100 px-2 text-sm text-red-700">désactivée</span>@endunless</h2>

    <dl class="grid gap-4 sm:grid-cols-3">
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Solde (crédits)</dt><dd class="text-xl font-semibold">{{ \App\Domain\Credits\CreditUnits::format($company->credit_balance_units) }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Envoi réel</dt><dd class="text-xl font-semibold">{{ $company->sending_status->label() }}</dd><dd class="text-xs text-slate-500">{{ $company->sending_status_reason }}</dd></div>
        <div class="rounded bg-white p-4 shadow"><dt class="text-sm text-slate-500">Pays</dt><dd class="text-xl font-semibold">{{ $company->country }}</dd></div>
    </dl>

    <section class="rounded bg-white p-4 shadow" aria-labelledby="members-title">
        <h3 id="members-title" class="mb-2 font-medium">Membres</h3>
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Nom</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">Rôle</th><th class="px-3 py-2">Compte</th></tr></thead>
            <tbody>
            @foreach ($members as $m)
                <tr class="border-b last:border-0" wire:key="m{{ $m->id }}"><td class="px-3 py-2">{{ $m->name }}</td><td class="px-3 py-2">{{ $m->email }}</td><td class="px-3 py-2">{{ $m->pivot->role }}</td><td class="px-3 py-2">{{ $m->disabled_at ? 'désactivé' : 'actif' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <form wire:submit.prevent class="space-y-3 rounded bg-white p-4 shadow">
        <h3 class="font-medium">{{ $company->is_active ? 'Désactiver' : 'Réactiver' }} l'entreprise</h3>
        <div>
            <label for="reason" class="text-sm font-medium">Motif (obligatoire)</label>
            <input id="reason" type="text" wire:model="reason" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        @if ($company->is_active)
            <button type="button" wire:click="disable" wire:confirm="Désactiver cette entreprise ?" class="rounded bg-red-700 px-4 py-2 font-medium text-white">Désactiver</button>
        @else
            <button type="button" wire:click="enable" class="rounded bg-slate-900 px-4 py-2 font-medium text-white">Réactiver</button>
        @endif
    </form>

    <section class="overflow-x-auto rounded bg-white shadow" aria-labelledby="audit-title">
        <h3 id="audit-title" class="px-3 pt-3 font-medium">Activité récente (journal d'audit)</h3>
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Action</th><th class="px-3 py-2">Acteur</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr class="border-b last:border-0" wire:key="l{{ $l->id }}"><td class="whitespace-nowrap px-3 py-2">{{ $l->created_at->format('d/m/Y H:i') }}</td><td class="px-3 py-2 font-mono text-xs">{{ $l->action }}</td><td class="px-3 py-2">{{ $l->actor?->name ?? 'Système' }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">Aucune activité.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
