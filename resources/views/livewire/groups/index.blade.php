<div class="max-w-2xl">
    <h2 class="mb-4 text-xl font-semibold">Groupes</h2>
    <form wire:submit="create" class="mb-4 flex gap-2">
        <input type="text" wire:model="name" placeholder="Nom du nouveau groupe" aria-label="Nom du groupe" required class="min-w-0 flex-1 rounded border border-slate-300 px-3 py-2">
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Créer</button>
    </form>
    @error('name') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Groupe</th><th class="px-3 py-2">Contacts</th><th></th></tr></thead>
            <tbody>
            @forelse ($groups as $g)
                <tr class="border-b last:border-0" wire:key="g{{ $g->id }}">
                    <td class="px-3 py-2"><a class="underline" href="{{ route('contacts.index', ['group' => $g->id]) }}">{{ $g->name }}</a></td>
                    <td class="px-3 py-2">{{ $g->contacts_count }}</td>
                    <td class="px-3 py-2 text-right"><button type="button" wire:click="delete({{ $g->id }})" wire:confirm="Supprimer ce groupe ? Les contacts sont conservés." class="text-red-600 underline">Supprimer</button></td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">Aucun groupe.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
