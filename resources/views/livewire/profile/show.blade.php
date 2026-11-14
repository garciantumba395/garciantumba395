<div class="max-w-xl space-y-8">
    <h2 class="text-xl font-semibold">Mon profil</h2>
    <p class="text-sm text-slate-600">{{ $user->name }} — {{ $user->email }}</p>

    {{-- 2FA obligatoire et pas encore activée : sa section passe en premier (c'est la seule action utile à ce stade). --}}
    @php($forcedTwoFactor = $required && ! $user->hasTwoFactorEnabled())
    @if ($forcedTwoFactor)
        @include('livewire.profile.two-factor-section')
    @endif

    <section aria-labelledby="pwd-title" class="space-y-4 rounded bg-white p-4 shadow-sm">
        <h3 id="pwd-title" class="font-semibold">Changer le mot de passe</h3>
        <form wire:submit="changePassword" class="space-y-3">
            <div>
                <label for="currentPassword" class="text-sm font-medium">Mot de passe actuel</label>
                <input id="currentPassword" type="password" wire:model="currentPassword" autocomplete="current-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('currentPassword') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="newPassword" class="text-sm font-medium">Nouveau mot de passe (10 caractères minimum)</label>
                <input id="newPassword" type="password" wire:model="newPassword" autocomplete="new-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('newPassword') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="newPassword_confirmation" class="text-sm font-medium">Confirmer le nouveau mot de passe</label>
                <input id="newPassword_confirmation" type="password" wire:model="newPassword_confirmation" autocomplete="new-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Changer le mot de passe</button>
        </form>
    </section>

    <section aria-labelledby="dev-title" class="space-y-4 rounded bg-white p-4 shadow-sm">
        <h3 id="dev-title" class="font-semibold">Autres appareils</h3>
        <p class="text-sm text-slate-600">Ferme toutes les autres sessions ouvertes avec ce compte (cet appareil reste connecté).</p>
        <form wire:submit="logoutOtherDevices" class="space-y-3">
            <div>
                <label for="logoutPassword" class="text-sm font-medium">Mot de passe</label>
                <input id="logoutPassword" type="password" wire:model="logoutPassword" autocomplete="current-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('logoutPassword') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded border border-slate-300 px-4 py-2 font-medium hover:bg-slate-100">Déconnecter les autres appareils</button>
        </form>
    </section>

    @unless ($forcedTwoFactor)
        @include('livewire.profile.two-factor-section')
    @endunless
</div>
