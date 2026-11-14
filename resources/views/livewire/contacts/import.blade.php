<div class="max-w-2xl space-y-6">
    <h2 class="text-xl font-semibold">Importer des contacts (CSV)</h2>
    <div class="rounded bg-white p-4 text-sm shadow">
        <p>Fichier CSV (UTF-8 ou Excel), 5 Mo et 10 000 lignes maximum, avec une ligne d'en-tête. Colonnes reconnues :
        <code>telephone</code> (obligatoire), <code>nom</code>, <code>email</code>. Séparateur « , » ou « ; ».</p>
        <p class="mt-2">Les numéros sont convertis au format international selon le pays de l'entreprise ({{ app(\App\Support\Tenancy\CurrentCompany::class)->company()->country }}).
        Réimporter un fichier ne crée pas de doublons. <strong>Par défaut, l'import n'enregistre aucun consentement</strong> : les contacts importés ne sont pas ciblables tant qu'une preuve n'est pas associée.</p>
    </div>
    <form wire:submit="import" class="space-y-4 rounded bg-white p-4 shadow">
        <div>
            <label for="file" class="text-sm font-medium">Fichier</label>
            <input id="file" type="file" wire:model="file" accept=".csv,.txt" class="mt-1 block w-full text-sm">
            @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="groupId" class="text-sm font-medium">Ajouter à un groupe (facultatif)</label>
            <select id="groupId" wire:model="groupId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">— Aucun —</option>
                @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
            </select>
        </div>
        <fieldset class="rounded border border-slate-200 p-3">
            <label class="flex items-start gap-2 text-sm font-medium">
                <input type="checkbox" wire:model.live="recordConsent" class="mt-1">
                <span>Je certifie disposer d'une preuve de consentement pour les contacts de ce fichier, et je souhaite l'enregistrer.</span>
            </label>
            @if ($recordConsent)
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="consentSource" class="text-sm font-medium">Source du consentement</label>
                        <select id="consentSource" wire:model="consentSource" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                            @foreach ($sources as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('consentSource') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="consentDate" class="text-sm font-medium">Date d'obtention</label>
                        <input id="consentDate" type="date" wire:model="consentDate" max="{{ now()->toDateString() }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                        @error('consentDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="consentEvidence" class="text-sm font-medium">Preuve (formulation présentée, référence du document…)</label>
                        <textarea id="consentEvidence" wire:model="consentEvidence" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                        @error('consentEvidence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Les numéros désinscrits sont ignorés : un consentement ne peut pas les réactiver.</p>
            @endif
        </fieldset>
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700" wire:loading.attr="disabled">Importer</button>
    </form>
    @if ($result)
        <div class="rounded border border-green-200 bg-green-50 p-4 text-sm" role="status">
            <p class="font-medium">Import terminé : {{ $result->filename }}</p>
            <ul class="mt-1 list-disc pl-5">
                <li>{{ $result->total_rows }} lignes lues</li>
                <li>{{ $result->created_count }} créés, {{ $result->updated_count }} mis à jour</li>
                <li>{{ $result->invalid_count }} invalides, {{ $result->duplicate_count }} doublons dans le fichier</li>
                <li>{{ $result->consent_count }} consentements enregistrés, {{ $result->suppressed_count }} numéros désinscrits ignorés</li>
            </ul>
            @if ($result->errors)
                <details class="mt-2"><summary class="cursor-pointer">Lignes en erreur</summary>
                    <ul class="mt-1 pl-5">@foreach ($result->errors as $e)<li>Ligne {{ $e['line'] }} : {{ $e['reason'] }}</li>@endforeach</ul>
                </details>
            @endif
        </div>
    @endif
    @if ($history->isNotEmpty())
        <div>
            <h3 class="mb-2 font-medium">Derniers imports</h3>
            <ul class="space-y-1 text-sm">
                @foreach ($history as $h)<li>{{ $h->created_at->format('d/m/Y H:i') }} — {{ $h->filename }} ({{ $h->created_count }} créés, {{ $h->invalid_count }} invalides)</li>@endforeach
            </ul>
        </div>
    @endif
</div>
