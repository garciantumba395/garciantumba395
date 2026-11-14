<div class="max-w-xl space-y-4">
    <h2 class="text-xl font-semibold">Identité légale de l'expéditeur</h2>
    <p class="text-sm text-slate-600">Le Code du numérique (art. 66) exige que toute publicité rende son expéditeur clairement identifiable. Ces informations apparaissent dans le pied de chaque message (le nom) et sur la page publique de désabonnement (l'ensemble). Voir <code>docs/CADRE_LEGAL_RDC.md</code>.</p>
    @if ($complete)
        <p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">Identité complète : les envois réels sont autorisés.</p>
    @else
        <p class="rounded bg-amber-50 p-3 text-sm text-amber-900" role="alert"><strong>Incomplète.</strong> Le nom, l'adresse et l'e-mail sont obligatoires pour tout <strong>envoi réel</strong> (la simulation n'est pas concernée).</p>
    @endif
    <form wire:submit="save" class="space-y-4 rounded bg-white p-4 shadow">
        <div><label for="legalName" class="text-sm font-medium">Nom ou raison sociale <span class="text-red-600">*</span></label>
            <input id="legalName" type="text" wire:model="legalName" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Affiché dans chaque message : un nom court économise des caractères (donc des crédits).</p>
            @error('legalName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
        <div><label for="legalAddress" class="text-sm font-medium">Adresse géographique <span class="text-red-600">*</span></label>
            <textarea id="legalAddress" wire:model="legalAddress" rows="2" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
            @error('legalAddress') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
        <div><label for="legalEmail" class="text-sm font-medium">Adresse e-mail de contact <span class="text-red-600">*</span></label>
            <input id="legalEmail" type="email" wire:model="legalEmail" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('legalEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
        <div><label for="legalPhone" class="text-sm font-medium">Téléphone de contact</label><input id="legalPhone" type="text" wire:model="legalPhone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            @error('legalPhone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
        <fieldset class="space-y-4 rounded border border-slate-200 p-3"><legend class="px-1 text-sm font-medium">Le cas échéant (art. 66 : « éventuellement »)</legend>
            <div><label for="legalRccm" class="text-sm font-medium">RCCM</label><input id="legalRccm" type="text" wire:model="legalRccm" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></div>
            <div><label for="legalTaxId" class="text-sm font-medium">Numéro d'impôt</label><input id="legalTaxId" type="text" wire:model="legalTaxId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></div>
            <div><label for="legalAct" class="text-sm font-medium">Acte juridique autorisant l'exercice de l'activité</label><input id="legalAct" type="text" wire:model="legalAct" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></div>
        </fieldset>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Enregistrer</button>
    </form>
</div>
