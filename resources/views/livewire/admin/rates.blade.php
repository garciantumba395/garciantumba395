<div class="space-y-6">
    <h2 class="text-xl font-semibold">Administration — tarifs par pays / opérateur</h2>
    <div class="rounded bg-white p-4 text-sm shadow">
        <p>Tarif par défaut : <strong>{{ number_format(\App\Domain\Credits\CreditUnits::smsPer100Credits((int) config('credits.default_units_per_segment')), 0, ',', ' ') }} SMS pour 100 crédits</strong> (1 SMS = 1 segment).
        Un tarif <em>par préfixe</em> l'emporte sur le tarif du pays, qui l'emporte sur le tarif par défaut. Aucun préfixe d'opérateur n'est pré-rempli : saisissez uniquement des valeurs que vous avez vérifiées.</p>
    </div>

    <form wire:submit="add" class="grid gap-3 rounded bg-white p-4 shadow sm:grid-cols-4">
        <div>
            <label for="country" class="text-sm font-medium">Pays</label>
            <select id="country" wire:model="country" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">@foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
            @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="prefix" class="text-sm font-medium">Préfixe (facultatif)</label>
            <input id="prefix" type="text" wire:model="prefix" placeholder="+243…" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('prefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="label" class="text-sm font-medium">Libellé (opérateur)</label>
            <input id="label" type="text" wire:model="label" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label for="smsPer100" class="text-sm font-medium">SMS pour 100 crédits</label>
            <input id="smsPer100" type="text" inputmode="numeric" wire:model="smsPer100" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('smsPer100') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-4"><button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Ajouter le tarif</button></div>
    </form>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Pays</th><th class="px-3 py-2">Préfixe</th><th class="px-3 py-2">Libellé</th><th class="px-3 py-2 text-right">SMS pour 100 crédits</th><th class="px-3 py-2">Actif</th></tr></thead>
            <tbody>
            @forelse ($rates as $r)
                <tr class="border-b last:border-0" wire:key="r{{ $r->id }}">
                    <td class="px-3 py-2">{{ $r->country }}</td>
                    <td class="px-3 py-2 font-mono">{{ $r->prefix ?: 'tout le pays' }}</td>
                    <td class="px-3 py-2">{{ $r->label ?: '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ \App\Domain\Credits\CreditUnits::smsPer100Credits($r->units_per_segment) }}</td>
                    <td class="px-3 py-2"><button type="button" wire:click="toggle({{ $r->id }})" class="underline">{{ $r->is_active ? 'Oui (désactiver)' : 'Non (activer)' }}</button></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Aucun tarif : le tarif par défaut s'applique partout.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
