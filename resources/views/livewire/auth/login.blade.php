<div>
    @if (session('status'))
        <div class="mb-4 rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
    @endif
    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="text-sm font-medium">Adresse e-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="text-sm font-medium">Mot de passe</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="remember"> Se souvenir de moi</label>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Se connecter</button>
    </form>
    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="underline">Mot de passe oublié ?</a>
        <a href="{{ route('register') }}" class="underline">Créer un compte</a>
    </div>
</div>
