<div class="max-w-2xl">
    <h2 class="mb-4 text-xl font-semibold">{{ $campaignId ? 'Modifier la campagne' : 'Nouvelle campagne' }}</h2>
    <form wire:submit="save" class="space-y-4">
        <div>
            <label for="name" class="text-sm font-medium">Nom (interne)</label>
            <input id="name" type="text" wire:model="name" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="body" class="text-sm font-medium">Message</label>
            <textarea id="body" wire:model.live.debounce.300ms="body" rows="5" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
            <p class="mt-1 text-xs text-slate-500">{{ $analysis['length'] }} caractères · {{ $analysis['segments'] }} segment(s) ({{ $analysis['encoding'] === 'gsm7' ? 'GSM-7' : 'Unicode : un caractère comme « ç », « â » ou un emoji réduit la taille d\'un segment à 70' }})</p>
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="groupId" class="text-sm font-medium">Destinataires</label>
            <select id="groupId" wire:model="groupId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">Tous les contacts</option>
                @foreach ($groups as $g)<option value="{{ $g->id }}">Groupe : {{ $g->name }}</option>@endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Seuls les contacts avec un consentement valide et non désinscrits recevront le message ; cela est revérifié à chaque envoi.</p>
            @error('groupId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="scheduledAt" class="text-sm font-medium">Programmer (facultatif, heure de {{ config('app.timezone') }})</label>
            <input id="scheduledAt" type="datetime-local" wire:model="scheduledAt" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('scheduledAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white">Enregistrer le brouillon</button>
            <a href="{{ route('campaigns.index') }}" class="rounded border border-slate-300 px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
