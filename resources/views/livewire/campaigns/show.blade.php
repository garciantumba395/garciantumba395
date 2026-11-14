<div class="max-w-3xl space-y-6" @if ($campaign->status->isActive()) wire:poll.5s @endif>
    <div>
        <a href="{{ route('campaigns.index') }}" class="text-sm underline">← Campagnes</a>
        <h2 class="mt-1 text-xl font-semibold">{{ $campaign->name }}</h2>
        <p class="mt-1"><span class="rounded bg-slate-100 px-2 py-1 text-sm font-medium">{{ $campaign->status->label() }}</span>
            @if ($campaign->started_at) <span class="ml-2 text-sm text-slate-600">lancée le {{ $campaign->started_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>@elseif ($campaign->scheduled_at) <span class="ml-2 text-sm text-slate-600">programmée le {{ $campaign->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>@endif</p>
    </div>

    @if ($live)
        <div class="rounded border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-900" role="alert">ENVOI RÉEL ACTIVÉ : les messages seront envoyés à de vraies personnes.</div>
    @else
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="status"><strong>Mode simulation</strong> : aucun SMS réel n'est envoyé. Les résultats ci-dessous sont des simulations.</div>
    @endif

    @if ($campaign->status === \App\Domain\Campaigns\CampaignStatus::Paused)
        <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-900" role="alert"><strong>Campagne en pause.</strong> {{ $campaign->pause_message }}</div>
    @endif
    @error('action') <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-900" role="alert">{{ $message }}</div> @enderror

    <section class="rounded bg-white p-4 shadow">
        <h3 class="mb-1 font-medium">Message</h3>
        <p class="whitespace-pre-line text-sm">{{ $campaign->body }}</p>
        <p class="mt-2 text-xs text-slate-500">Destinataires : {{ $group ? 'groupe « '.$group->name.' »' : 'tous les contacts' }}</p>
    </section>

    @if ($estimate)
    <section class="rounded bg-white p-4 shadow">
        <h3 class="mb-1 font-medium">Estimation avant lancement</h3>
        <ul class="list-disc pl-5 text-sm">
            <li>{{ $estimate['recipients'] }} destinataire(s) ciblable(s) maintenant ; {{ $estimate['excluded'] }} exclu(s) (sans consentement ou désinscrits)</li>
            <li>Coût estimé : {{ \App\Domain\Credits\CreditUnits::format($estimate['cost_units']) }} crédits — solde : {{ \App\Domain\Credits\CreditUnits::format($estimate['balance_units']) }}</li>
        </ul>
        @unless ($estimate['enough'])<p class="mt-2 text-sm font-medium text-red-700">Solde insuffisant pour tout envoyer : la campagne se mettra en pause à court de crédits.</p>@endunless
        <p class="mt-2 text-xs text-slate-500">Estimation indicative : les destinataires et le consentement sont revérifiés à chaque envoi.</p>
    </section>
    @endif

    @unless ($campaign->status === \App\Domain\Campaigns\CampaignStatus::Draft)
    <section class="rounded bg-white p-4 shadow">
        <h3 class="mb-2 font-medium">Résultats @if ($stats['simulated'])<span class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-900">simulation</span>@endif</h3>
        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-slate-500">Destinataires</dt><dd class="text-xl font-semibold">{{ $stats['recipients'] }}</dd></div>
            <div><dt class="text-slate-500">En attente</dt><dd class="text-xl font-semibold">{{ $stats['pending'] }}</dd></div>
            <div><dt class="text-slate-500">Envoyés</dt><dd class="text-xl font-semibold">{{ $stats['sent'] + $stats['delivered'] }}</dd></div>
            <div><dt class="text-slate-500">Délivrés</dt><dd class="text-xl font-semibold">{{ $stats['delivered'] }}</dd></div>
            <div><dt class="text-slate-500">Échecs</dt><dd class="text-xl font-semibold">{{ $stats['failed'] }}</dd></div>
            <div><dt class="text-slate-500">Résultat inconnu</dt><dd class="text-xl font-semibold">{{ $stats['unknown'] }}</dd></div>
            <div><dt class="text-slate-500">Bloqués à l'envoi</dt><dd class="text-xl font-semibold">{{ $stats['blocked_consent'] }}</dd></div>
            <div><dt class="text-slate-500">Exclus à la préparation</dt><dd class="text-xl font-semibold">{{ $campaign->excluded_count }}</dd></div>
        </dl>
        <p class="mt-2 text-sm"><a href="{{ route('reports.index', ['campaign' => $campaign->id, 'mode' => $stats['simulated'] ? 'simulated' : 'real', 'from' => $reportFrom, 'to' => $reportTo]) }}" class="underline">Voir le rapport détaillé de cette campagne</a></p>
        <p class="mt-2 text-xs text-slate-500">Chiffres issus du journal des messages. « Délivrés » dépend des accusés de réception du fournisseur (aucun en simulation).</p>
    </section>
    @endunless

    @if ($canManage)
    <div class="flex flex-wrap gap-2">
        @if ($campaign->status === \App\Domain\Campaigns\CampaignStatus::Draft)
            <a href="{{ route('campaigns.edit', $campaign->id) }}" class="rounded border border-slate-300 px-4 py-2">Modifier</a>
            <button type="button" wire:click="launch" wire:confirm="Lancer cette campagne ?" class="rounded bg-slate-900 px-4 py-2 font-medium text-white" wire:loading.attr="disabled">{{ $campaign->scheduled_at ? 'Programmer' : 'Lancer maintenant' }}</button>
        @endif
        @if ($campaign->status->isActive())
            <button type="button" wire:click="pause" class="rounded border border-slate-300 px-4 py-2">Mettre en pause</button>
        @endif
        @if ($campaign->status === \App\Domain\Campaigns\CampaignStatus::Paused)
            <button type="button" wire:click="resume" class="rounded bg-slate-900 px-4 py-2 font-medium text-white">Reprendre</button>
        @endif
        @if (in_array($campaign->status, [\App\Domain\Campaigns\CampaignStatus::Scheduled, \App\Domain\Campaigns\CampaignStatus::Preparing, \App\Domain\Campaigns\CampaignStatus::Sending, \App\Domain\Campaigns\CampaignStatus::Paused], true))
            <button type="button" wire:click="cancel" wire:confirm="Annuler la campagne ? Les messages non envoyés ne le seront plus." class="rounded border border-red-300 px-4 py-2 text-red-700">Annuler</button>
        @endif
    </div>
    @else
        <p class="text-sm text-slate-500">Seuls les propriétaires et administrateurs peuvent lancer ou arrêter une campagne.</p>
    @endif
</div>
