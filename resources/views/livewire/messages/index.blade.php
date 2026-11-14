<div class="space-y-6">
    <h2 class="text-xl font-semibold">Messages</h2>

    @if ($live)
        <div class="rounded border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-900" role="alert">ENVOI RÉEL ACTIVÉ : les messages sont envoyés à de vraies personnes.</div>
    @else
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="status">
            <strong>Mode simulation</strong> : aucun SMS réel n'est envoyé. Les messages ci-dessous sont des simulations, ils ne comptent pas comme des envois réels.
        </div>
    @endif

    @if ($canTest)
    <form wire:submit="sendTest" class="space-y-3 rounded bg-white p-4 shadow">
        <h3 class="font-medium">Envoi de test</h3>
        <div>
            <label for="contactId" class="text-sm font-medium">Contact</label>
            <select id="contactId" wire:model="contactId" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">— Choisir —</option>
                @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name ?: 'Sans nom' }} ({{ $c->phone }})</option>@endforeach
            </select>
            @error('contactId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="body" class="text-sm font-medium">Message</label>
            <textarea id="body" wire:model="body" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded bg-slate-900 px-4 py-2 font-medium text-white" wire:loading.attr="disabled">Envoyer le test</button>
        <p class="text-xs text-slate-500">Le consentement et la désinscription sont revérifiés au moment de l'envoi.</p>
    </form>
    @endif

    <div>
        <label for="status" class="text-sm font-medium">Statut</label>
        <select id="status" wire:model.live="status" class="ml-2 rounded border border-slate-300 px-3 py-2">
            <option value="">Tous</option>
            @foreach ($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Numéro</th><th class="px-3 py-2">Message</th><th class="px-3 py-2">Segments</th><th class="px-3 py-2">Statut</th><th class="px-3 py-2">Mode</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr class="border-b align-top last:border-0" wire:key="m{{ $m->id }}">
                    <td class="whitespace-nowrap px-3 py-2">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2 font-mono">{{ $m->phone }}</td>
                    <td class="max-w-xs truncate px-3 py-2" title="{{ $m->body }}">{{ $m->body }}</td>
                    <td class="px-3 py-2">{{ $m->segments ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $m->status->label() }}@if ($m->blocked_reason) <span class="text-slate-500">({{ ['suppressed' => 'désinscrit', 'no_consent' => 'sans consentement', 'insufficient_credits' => 'crédits insuffisants'][$m->blocked_reason] ?? $m->blocked_reason }})</span>@endif
                        @if ($m->error_message)<div class="text-xs text-red-700">{{ $m->error_message }}</div>@endif</td>
                    <td class="px-3 py-2">@if ($m->simulated)<span class="rounded bg-amber-100 px-2 py-0.5 text-amber-900">Simulation</span>@else<span class="rounded bg-red-100 px-2 py-0.5 text-red-900">Réel</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Aucun message.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $messages->links() }}</div>
</div>
