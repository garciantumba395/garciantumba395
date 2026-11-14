<div class="max-w-md">
    <h2 class="mb-4 text-xl font-semibold">{{ $contactId ? 'Modifier le contact' : 'Nouveau contact' }}</h2>
    <form wire:submit="save" class="space-y-4">
        <div>
            <label for="name" class="text-sm font-medium">Nom</label>
            <input id="name" type="text" wire:model="name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="text-sm font-medium">Téléphone mobile</label>
            <input id="phone" type="tel" wire:model="phone" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="text-sm font-medium">E-mail (facultatif)</label>
            <input id="email" type="email" wire:model="email" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        @if ($groups->isNotEmpty())
            <fieldset>
                <legend class="text-sm font-medium">Groupes</legend>
                @foreach ($groups as $g)
                    <label class="mr-4 inline-flex items-center gap-1 text-sm"><input type="checkbox" wire:model="groupIds" value="{{ $g->id }}"> {{ $g->name }}</label>
                @endforeach
            </fieldset>
        @endif
        <div class="flex gap-2">
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Enregistrer</button>
            <a href="{{ route('contacts.index') }}" class="rounded border border-slate-300 px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
