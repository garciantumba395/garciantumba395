<div class="space-y-6">
    <h2 class="text-xl font-semibold">Administration — utilisateurs</h2>
    <p class="text-sm text-slate-600">Un compte désactivé ne peut plus se connecter et sa session en cours est invalidée. Vous ne pouvez pas désactiver votre propre compte ni le dernier administrateur de la plateforme. Le statut d'administrateur ne s'attribue qu'en console (<code>platform:grant-admin</code>).</p>

    <div class="rounded bg-white p-4 shadow">
        <label for="search" class="text-sm font-medium">Rechercher (nom ou e-mail)</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 sm:w-1/2">
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Nom</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">Entreprises</th><th class="px-3 py-2">Admin plateforme</th><th class="px-3 py-2">Compte</th><th class="px-3 py-2">Motif</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr class="border-b last:border-0" wire:key="u{{ $u->id }}">
                    <td class="px-3 py-2">{{ $u->name }}</td>
                    <td class="px-3 py-2">{{ $u->email }}</td>
                    <td class="px-3 py-2">{{ $u->companies_count }}</td>
                    <td class="px-3 py-2">{{ $u->is_platform_admin ? 'oui' : 'non' }}</td>
                    <td class="px-3 py-2">{{ $u->disabled_at ? 'désactivé' : 'actif' }}</td>
                    <td class="px-3 py-2">
                        <label class="sr-only" for="reason{{ $u->id }}">Motif pour {{ $u->name }}</label>
                        <input id="reason{{ $u->id }}" type="text" wire:model="reasons.{{ $u->id }}" placeholder="{{ $u->disabled_reason ?: 'Motif obligatoire' }}" class="w-full rounded border border-slate-300 px-2 py-1">
                        @error("reasons.$u->id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </td>
                    <td class="px-3 py-2 text-right">
                        @if ($u->disabled_at)
                            <button type="button" wire:click="enable({{ $u->id }})" class="underline">Réactiver</button>
                        @elseif ($u->id !== auth()->id())
                            <button type="button" wire:click="disable({{ $u->id }})" wire:confirm="Désactiver ce compte ?" class="text-red-700 underline">Désactiver</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
