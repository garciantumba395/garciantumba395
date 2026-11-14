<div>
    <p class="mb-4 text-sm text-slate-600">Saisissez le code à 6 chiffres de votre application d'authentification, ou l'un de vos codes de secours (à usage unique).</p>
    <form wire:submit="verify" class="space-y-4">
        <div>
            <label for="code" class="text-sm font-medium">Code de vérification</label>
            <input id="code" type="text" wire:model="code" inputmode="text" autocomplete="one-time-code" autofocus required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 tracking-widest">
            @error('code') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Vérifier</button>
    </form>
    <p class="mt-4 text-sm"><a href="{{ route('login') }}" class="underline">Retour à la connexion</a></p>
</div>
