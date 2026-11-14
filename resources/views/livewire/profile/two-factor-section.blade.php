<section aria-labelledby="tfa-title" @class(['space-y-4 rounded bg-white p-4 shadow-sm', 'ring-2 ring-slate-900' => $forcedTwoFactor])>
    <h3 id="tfa-title" class="font-semibold">Double authentification (TOTP)</h3>

    @if (count($recoveryCodes))
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm" role="alert">
            <p class="font-medium">Codes de secours — affichés une seule fois.</p>
            <p class="mb-2">Conservez-les en lieu sûr : chacun ne peut servir qu'une fois si vous perdez votre téléphone.</p>
            <ul class="grid grid-cols-2 gap-1 font-mono">
                @foreach ($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach
            </ul>
            <button type="button" wire:click="dismissRecoveryCodes" class="mt-3 rounded border border-slate-300 bg-white px-3 py-1">J'ai noté mes codes</button>
        </div>
    @endif

    @if ($enabled)
        <p class="text-sm text-green-800">Activée. Codes de secours restants : {{ $remaining }}.</p>
        @if ($required)<p class="text-sm text-slate-600">Obligatoire pour votre rôle : elle ne peut pas être désactivée.</p>@endif
        <form class="space-y-3">
            <p class="text-sm text-slate-600">Pour désactiver la 2FA ou régénérer les codes de secours, confirmez avec votre mot de passe et un code (de l'application ou de secours).</p>
            <div>
                <label for="twoFactorPassword" class="text-sm font-medium">Mot de passe</label>
                <input id="twoFactorPassword" type="password" wire:model="twoFactorPassword" autocomplete="current-password" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('twoFactorPassword') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="twoFactorCode" class="text-sm font-medium">Code</label>
                <input id="twoFactorCode" type="text" wire:model="twoFactorCode" autocomplete="one-time-code" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('twoFactorCode') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="regenerateRecoveryCodes" class="rounded border border-slate-300 px-4 py-2 hover:bg-slate-100">Régénérer les codes de secours</button>
                @unless ($required)<button type="button" wire:click="disableTwoFactor" class="rounded bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700">Désactiver la 2FA</button>@endunless
            </div>
        </form>
    @elseif ($pendingSetup)
        <ol class="list-decimal space-y-2 pl-5 text-sm">
            <li>Dans votre application d'authentification, ajoutez un compte par <strong>saisie manuelle</strong> avec cette clé (type : basé sur l'heure) :
                <p class="mt-1 break-all rounded bg-slate-100 p-2 font-mono" data-testid="two-factor-secret">{{ $secret }}</p>
                <p class="mt-1 text-xs text-slate-500">Ou ouvrez cette adresse otpauth sur un appareil qui la gère : <span class="break-all font-mono">{{ $uri }}</span></p>
            </li>
            <li>Saisissez ci-dessous le code à 6 chiffres affiché pour confirmer.</li>
        </ol>
        <form wire:submit="confirmTwoFactor" class="space-y-3">
            <div>
                <label for="setupCode" class="text-sm font-medium">Code de confirmation</label>
                <input id="setupCode" type="text" wire:model="setupCode" inputmode="numeric" autocomplete="one-time-code" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('setupCode') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Confirmer et activer</button>
                <button type="button" wire:click="cancelTwoFactorSetup" class="rounded border border-slate-300 px-4 py-2 hover:bg-slate-100">Annuler</button>
            </div>
        </form>
    @else
        <p class="text-sm text-slate-600">Non activée. Elle ajoute un code temporaire à la connexion.@if ($required) <strong>Elle est obligatoire pour votre rôle.</strong>@endif</p>
        <button type="button" wire:click="startTwoFactor" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Activer la double authentification</button>
    @endif
</section>
