<div>
    <form wire:submit="resetPassword" class="space-y-4">
        <div>
            <label for="email" class="text-sm font-medium">Adresse e-mail</label>
            <input id="email" type="email" wire:model="email" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="text-sm font-medium">Nouveau mot de passe</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="text-sm font-medium">Confirmer</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Modifier le mot de passe</button>
    </form>
</div>
