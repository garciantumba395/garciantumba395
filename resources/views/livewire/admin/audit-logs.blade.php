<div class="space-y-6">
    <h2 class="text-xl font-semibold">Administration — journal d'audit</h2>
    <p class="text-sm text-slate-600">Lecture seule. Le journal ne contient ni secret, ni mot de passe, ni texte de message, ni numéro de téléphone : uniquement des identifiants et des valeurs techniques. Dates dans le fuseau {{ config('app.timezone') }}.</p>

    <div class="grid gap-3 rounded bg-white p-4 shadow sm:grid-cols-5">
        <div>
            <label for="action" class="text-sm font-medium">Action</label>
            <select id="action" wire:model.live="action" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                <option value="">Toutes</option>
                @foreach ($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="companyId" class="text-sm font-medium">Entreprise</label>
            <select id="companyId" wire:model.live="companyId" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                <option value="">Toutes</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="actorId" class="text-sm font-medium">Acteur</label>
            <select id="actorId" wire:model.live="actorId" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
                <option value="">Tous</option>
                <option value="system">Système</option>
                @foreach ($actors as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="dateFrom" class="text-sm font-medium">Du</label>
            <input id="dateFrom" type="date" wire:model.live="dateFrom" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
            @error('dateFrom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="dateTo" class="text-sm font-medium">Au</label>
            <input id="dateTo" type="date" wire:model.live="dateTo" class="mt-1 w-full rounded border border-slate-300 px-2 py-2">
            @error('dateTo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Acteur</th><th class="px-3 py-2">Entreprise</th><th class="px-3 py-2">Action</th><th class="px-3 py-2">Sujet</th><th class="px-3 py-2">Détails</th><th class="px-3 py-2">IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr class="border-b last:border-0" wire:key="a{{ $l->id }}">
                    <td class="whitespace-nowrap px-3 py-2">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2">{{ $l->actor?->name ?? 'Système' }}</td>
                    <td class="px-3 py-2">{{ $l->company?->name ?? '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $l->action }}</td>
                    <td class="px-3 py-2">{{ $l->subject_type ? $l->subject_type.' #'.$l->subject_id : '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $l->details ? json_encode($l->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</td>
                    <td class="px-3 py-2">{{ $l->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Aucune entrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
