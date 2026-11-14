<div class="space-y-6">
    <h2 class="text-xl font-semibold">Crédits</h2>
    <div class="grid gap-3 sm:max-w-xl sm:grid-cols-2">
        <div class="rounded bg-white p-4 shadow">
            <p class="text-sm text-slate-500">Solde</p>
            <p class="text-2xl font-semibold">{{ $balanceText }} <span class="text-base font-normal">crédits</span></p>
        </div>
        <div class="rounded bg-white p-4 shadow">
            <p class="text-sm text-slate-500">Environ</p>
            <p class="text-2xl font-semibold">{{ number_format($estimatedSms, 0, ',', ' ') }} <span class="text-base font-normal">SMS</span></p>
            <p class="text-xs text-slate-500">Estimation au tarif par défaut ; le coût réel dépend du pays, de l'opérateur et de la longueur du message (1 segment = 1 SMS).</p>
        </div>
    </div>
    <p class="text-sm text-slate-600">Les crédits sont ajoutés par l'administrateur de la plateforme.</p>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Opération</th><th class="px-3 py-2 text-right">Montant</th><th class="px-3 py-2 text-right">Solde après</th><th class="px-3 py-2">Détail</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr class="border-b last:border-0" wire:key="e{{ $e->id }}">
                    <td class="whitespace-nowrap px-3 py-2">{{ $e->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2">{{ $e->type->label() }}@if ($e->simulated) <span class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-900">simulation</span>@endif</td>
                    <td class="px-3 py-2 text-right font-mono {{ $e->amount_units < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $e->amount_units > 0 ? '+' : '' }}{{ \App\Domain\Credits\CreditUnits::format($e->amount_units) }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ \App\Domain\Credits\CreditUnits::format($e->balance_after_units) }}</td>
                    <td class="px-3 py-2">{{ $e->reason ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Aucune opération.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $entries->links() }}</div>
</div>
