<div class="max-w-3xl space-y-6">
    <div>
        <a href="{{ route('contacts.index') }}" class="text-sm underline">← Contacts</a>
        <h2 class="mt-1 text-xl font-semibold">{{ $contact->name ?: 'Sans nom' }} <span class="font-mono text-base font-normal">{{ $contact->phone }}</span></h2>
        <p class="mt-2">
            <span class="rounded px-2 py-1 text-sm font-medium {{ $eligibility === $Eligible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $eligibility->label() }}</span>
            @if ($suppression)<span class="ml-2 text-sm text-slate-600">depuis le {{ $suppression->created_at->format('d/m/Y') }}</span>@endif
        </p>
    </div>

    <section class="rounded bg-white p-4 shadow">
        <h3 class="mb-2 font-medium">Historique des consentements</h3>
        @forelse ($consents as $c)
            <div class="border-b py-2 text-sm last:border-0" wire:key="k{{ $c->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span><strong>{{ $c->sourceLabel() }}</strong> — obtenu le {{ $c->obtained_at->format('d/m/Y') }}</span>
                    @if ($c->revoked_at)
                        <span class="text-red-700">Révoqué le {{ $c->revoked_at->format('d/m/Y') }}{{ $c->revoke_reason ? ' ('.$c->revoke_reason.')' : '' }}</span>
                    @else
                        <button type="button" wire:click="revoke({{ $c->id }})" wire:confirm="Révoquer ce consentement ?" class="text-red-600 underline">Révoquer</button>
                    @endif
                </div>
                @if ($c->evidence)<p class="mt-1 whitespace-pre-line text-slate-600">{{ $c->evidence }}</p>@endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucun consentement enregistré : ce contact n'est pas ciblable.</p>
        @endforelse
    </section>

    @unless ($suppression)
    <section class="rounded bg-white p-4 shadow">
        <h3 class="mb-2 font-medium">Enregistrer un consentement</h3>
        <form wire:submit="recordConsent" class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="source" class="text-sm font-medium">Source</label>
                    <select id="source" wire:model="source" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">@foreach ($sources as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                    @error('source') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="obtainedAt" class="text-sm font-medium">Date d'obtention</label>
                    <input id="obtainedAt" type="date" wire:model="obtainedAt" max="{{ now()->toDateString() }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    @error('obtainedAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="evidence" class="text-sm font-medium">Preuve (formulation présentée, référence du document…)</label>
                <textarea id="evidence" wire:model="evidence" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                @error('evidence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Enregistrer le consentement</button>
        </form>
    </section>
    <section class="rounded border border-red-200 bg-red-50 p-4">
        <h3 class="font-medium text-red-900">Désinscrire ce numéro</h3>
        <p class="my-2 text-sm text-red-900">Bloque tout envoi à ce numéro et révoque ses consentements. Seul un administrateur peut lever la désinscription.</p>
        <button type="button" wire:click="optOut" wire:confirm="Désinscrire ce numéro ?" class="rounded bg-red-700 px-4 py-2 text-white">Désinscrire</button>
    </section>
    @endunless
</div>
