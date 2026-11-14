<div class="max-w-3xl space-y-6">
    <h2 class="text-xl font-semibold">Liste de désinscription</h2>
    <p class="text-sm text-slate-600">Les numéros listés ne reçoivent aucun envoi, même s'ils ont un consentement. La liste est liée au numéro : elle survit à la suppression ou au réimport du contact.</p>

    <form wire:submit="add" class="space-y-3 rounded bg-white p-4 shadow">
        <div>
            <label for="numbers" class="text-sm font-medium">Numéros à désinscrire (un par ligne, ou séparés par des virgules)</label>
            <textarea id="numbers" wire:model="numbers" rows="3" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
            @error('numbers') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="reason" class="text-sm font-medium">Motif (facultatif)</label>
            <input id="reason" type="text" wire:model="reason" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Désinscrire</button>
    </form>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Numéro</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Motif</th><th class="px-3 py-2"></th></tr></thead>
            <tbody>
            @forelse ($suppressions as $s)
                <tr class="border-b align-top last:border-0" wire:key="s{{ $s->id }}">
                    <td class="px-3 py-2 font-mono">{{ $s->phone }}</td>
                    <td class="px-3 py-2">{{ $s->created_at->format('d/m/Y') }}</td>
                    <td class="px-3 py-2">{{ $s->reason ?: '—' }}</td>
                    <td class="px-3 py-2">
                        @if ($canLift)
                            <input type="text" wire:model="liftReasons.{{ $s->id }}" placeholder="Motif de levée" aria-label="Motif de levée" class="w-40 rounded border border-slate-300 px-2 py-1">
                            <button type="button" wire:click="lift({{ $s->id }})" wire:confirm="Lever la désinscription ? Un nouveau consentement sera requis." class="underline">Lever</button>
                            @error("liftReasons.{$s->id}") <p class="text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Aucun numéro désinscrit.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $suppressions->links() }}</div>
</div>
