<div class="max-w-md">
    <h2 class="mb-4 text-xl font-semibold">Nouvelle entreprise</h2>
    <form wire:submit="save" class="space-y-4">
        <div>
            <label for="name" class="text-sm font-medium">Nom de l'entreprise</label>
            <input id="name" type="text" wire:model="name" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Créer</button>
    </form>
</div>
