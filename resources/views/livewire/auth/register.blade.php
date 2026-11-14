<div>
    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="name" class="text-sm font-medium">Votre nom</label>
            <input id="name" type="text" wire:model="name" autocomplete="name" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="company_name" class="text-sm font-medium">Nom de l'entreprise</label>
            <input id="company_name" type="text" wire:model="company_name" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="text-sm font-medium">Adresse e-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="text-sm font-medium">Mot de passe (10 caractères minimum)</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="text-sm font-medium">Confirmer le mot de passe</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Créer mon compte</button>
    </form>
    <p class="mt-4 text-sm"><a href="{{ route('login') }}" class="underline">Déjà un compte ? Se connecter</a></p>
</div>
