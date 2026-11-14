<div>
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-semibold">Campagnes</h2>
        <a href="{{ route('campaigns.create') }}" class="rounded bg-slate-900 px-4 py-2 font-medium text-white">Nouvelle campagne</a>
    </div>
    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Nom</th><th class="px-3 py-2">Statut</th><th class="px-3 py-2">Programmée</th><th class="px-3 py-2">Destinataires</th><th></th></tr></thead>
            <tbody>
            @forelse ($campaigns as $c)
                <tr class="border-b last:border-0" wire:key="c{{ $c->id }}">
                    <td class="px-3 py-2"><a class="underline" href="{{ route('campaigns.show', $c->id) }}">{{ $c->name }}</a></td>
                    <td class="px-3 py-2">{{ $c->status->label() }}</td>
                    <td class="px-3 py-2">{{ $c->scheduled_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $c->status === \App\Domain\Campaigns\CampaignStatus::Draft ? '—' : $c->recipient_count }}</td>
                    <td class="px-3 py-2 text-right">
                        @if ($c->status === \App\Domain\Campaigns\CampaignStatus::Draft)
                            <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Supprimer ce brouillon ?" class="text-red-600 underline">Supprimer</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Aucune campagne.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $campaigns->links() }}</div>
</div>
