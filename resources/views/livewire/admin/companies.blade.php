<div class="space-y-6">
    <h2 class="text-xl font-semibold">Administration — entreprises et crédits</h2>
    <p class="text-sm text-slate-600"><a class="underline" href="{{ route('admin.rates') }}">Gérer les tarifs par pays / opérateur</a></p>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Entreprise</th><th class="px-3 py-2">Pays</th><th class="px-3 py-2">Membres</th><th class="px-3 py-2 text-right">Solde (crédits)</th><th></th></tr></thead>
            <tbody>
            @foreach ($companies as $c)
                <tr class="border-b last:border-0 {{ $companyId === $c->id ? 'bg-slate-50' : '' }}" wire:key="co{{ $c->id }}">
                    <td class="px-3 py-2">{{ $c->name }}</td>
                    <td class="px-3 py-2">{{ $c->country }}</td>
                    <td class="px-3 py-2">{{ $c->users_count }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ \App\Domain\Credits\CreditUnits::format($c->credit_balance_units) }}</td>
                    <td class="px-3 py-2 text-right"><button type="button" wire:click="$set('companyId', {{ $c->id }})" class="underline">Sélectionner</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($companyId)
    <form wire:submit="submit" class="space-y-3 rounded bg-white p-4 shadow">
        <h3 class="font-medium">Ajouter ou corriger des crédits</h3>
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label for="operation" class="text-sm font-medium">Opération</label>
                <select id="operation" wire:model="operation" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"><option value="credit">Ajout de crédits</option><option value="retrait">Retrait (correction)</option></select>
            </div>
            <div class="sm:col-span-2">
                <label for="amount" class="text-sm font-medium">Montant en crédits (2 décimales max.)</label>
                <input id="amount" type="text" inputmode="decimal" wire:model="amount" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="reason" class="text-sm font-medium">Motif / référence (obligatoire)</label>
            <input id="reason" type="text" wire:model="reason" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700" wire:loading.attr="disabled">Enregistrer</button>
    </form>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Opération</th><th class="px-3 py-2 text-right">Montant</th><th class="px-3 py-2 text-right">Solde après</th><th class="px-3 py-2">Détail</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr class="border-b last:border-0" wire:key="en{{ $e->id }}">
                    <td class="whitespace-nowrap px-3 py-2">{{ $e->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2">{{ $e->type->label() }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ $e->amount_units > 0 ? '+' : '' }}{{ \App\Domain\Credits\CreditUnits::format($e->amount_units) }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ \App\Domain\Credits\CreditUnits::format($e->balance_after_units) }}</td>
                    <td class="px-3 py-2">{{ $e->reason ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Aucune opération.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
