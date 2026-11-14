<div class="max-w-md">
    <h2 class="mb-4 text-xl font-semibold">Paramètres de l'entreprise</h2>
    <form wire:submit="save" class="space-y-4">
        <div>
            <label for="name" class="text-sm font-medium">Nom</label>
            <input id="name" type="text" wire:model="name" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="country" class="text-sm font-medium">Pays et indicatif</label>
            <select id="country" wire:model="country" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @foreach ($countries as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Sert à convertir les numéros saisis sans indicatif (ex. 0812345678).</p>
            @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Enregistrer</button>
    </form>
    <p class="mt-6 text-sm"><a href="{{ route('companies.create') }}" class="underline">Créer une autre entreprise</a></p>
</div>
