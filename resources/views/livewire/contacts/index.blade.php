<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-semibold">Contacts <span class="text-sm font-normal text-slate-500">({{ $total }})</span></h2>
        <div class="flex gap-2">
            <a href="{{ route('contacts.import') }}" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm">Importer un CSV</a>
            <a href="{{ route('contacts.create') }}" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Ajouter</a>
        </div>
    </div>
    <div class="mb-4 flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher (nom, numéro, e-mail)" aria-label="Rechercher" class="min-w-0 flex-1 rounded border border-slate-300 px-3 py-2">
        <select wire:model.live="group" aria-label="Filtrer par groupe" class="rounded border border-slate-300 px-3 py-2">
            <option value="">Tous les groupes</option>
            @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
        </select>
    </div>
    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Nom</th><th class="px-3 py-2">Téléphone</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2"></th></tr></thead>
            <tbody>
            @forelse ($contacts as $c)
                <tr class="border-b last:border-0" wire:key="c{{ $c->id }}">
                    <td class="px-3 py-2">{{ $c->name ?: '—' }}</td>
                    <td class="px-3 py-2 font-mono">{{ $c->phone }}</td>
                    <td class="px-3 py-2">{{ $c->email ?: '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <a href="{{ route('contacts.edit', $c->id) }}" class="underline">Modifier</a>
                        <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Supprimer ce contact ?" class="ml-2 text-red-600 underline">Supprimer</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Aucun contact.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $contacts->links() }}</div>
</div>
