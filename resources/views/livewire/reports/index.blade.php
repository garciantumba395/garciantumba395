<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold">Rapports d'envoi</h2>
        <p class="mt-1 text-sm text-slate-600">Chiffres issus uniquement du journal des messages et du grand livre de crédits de votre entreprise. Les envois simulés et les envois réels sont toujours présentés séparément.</p>
    </div>

    @if (session('export_error'))
        <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-900" role="alert">{{ session('export_error') }}</div>
    @endif

    <form wire:submit="apply" class="grid gap-3 rounded bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-4" aria-label="Filtres du rapport">
        <div>
            <label for="from" class="text-sm font-medium">Du</label>
            <input id="from" type="date" wire:model="from" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            @error('from') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="to" class="text-sm font-medium">Au (inclus)</label>
            <input id="to" type="date" wire:model="to" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            @error('to') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="campaign" class="text-sm font-medium">Campagne</label>
            <select id="campaign" wire:model="campaign" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="">Toutes (y compris les envois hors campagne)</option>
                @foreach ($campaigns as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @error('campaign') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white" wire:loading.attr="disabled">Afficher</button>
        </div>
        <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-4">Les jours sont ceux du fuseau de l'application ({{ config('app.timezone') }}), la date de fin est incluse. La date d'un message est celle de sa création au journal.</p>
    </form>

    @error('mode') <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-900" role="alert">{{ $message }}</div> @enderror

    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Nature des envois">
        <button type="button" role="tab" aria-selected="{{ $simulated ? 'false' : 'true' }}" wire:click="showMode('real')"
                class="rounded px-4 py-2 text-sm font-medium {{ $simulated ? 'border border-slate-300 bg-white' : 'bg-red-800 text-white' }}">Envois réels</button>
        <button type="button" role="tab" aria-selected="{{ $simulated ? 'true' : 'false' }}" wire:click="showMode('simulated')"
                class="rounded px-4 py-2 text-sm font-medium {{ $simulated ? 'bg-amber-700 text-white' : 'border border-slate-300 bg-white' }}">Simulation</button>
    </div>

    @if ($valid)
        @if ($simulated)
            <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900" role="status">Simulation : aucun SMS réel n'a été envoyé. Ces chiffres ne comptent pas comme des envois réels.</div>
        @else
            <div class="rounded border border-slate-200 bg-slate-100 p-3 text-sm text-slate-700" role="status">Envois réels uniquement : les messages simulés sont exclus de ces chiffres.</div>
        @endif

        @if ($campaignName)
            <p class="text-sm">Rapport de la campagne <strong>{{ $campaignName }}</strong> —
                <a href="{{ route('campaigns.show', $filters->campaignId) }}" class="underline">voir la campagne</a>.</p>
        @endif

        @if ($summary->isEmpty())
            <div class="rounded bg-white p-6 text-center shadow">
                <p class="font-medium">Aucune donnée sur cette période</p>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($simulated) Aucun message simulé du {{ \Carbon\Carbon::parse($filters->from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($filters->to)->format('d/m/Y') }}.
                    @else Aucun envoi réel du {{ \Carbon\Carbon::parse($filters->from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($filters->to)->format('d/m/Y') }}.@endif
                    @if ($otherNatureHasData) Des données existent dans l'autre onglet ({{ $simulated ? 'envois réels' : 'simulation' }}) pour cette période. @endif
                </p>
            </div>
        @else
            <section class="rounded bg-white p-4 shadow" aria-labelledby="totals-title">
                <h3 id="totals-title" class="mb-3 font-medium">Totaux — {{ $simulated ? 'simulation' : 'envois réels' }}</h3>
                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-4">
                    <div><dt class="text-slate-500">Messages au journal</dt><dd class="text-xl font-semibold">{{ number_format($summary->total, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Envoyés (acceptés par le fournisseur)</dt><dd class="text-xl font-semibold">{{ number_format($summary->sent, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Délivrés (accusé reçu)</dt><dd class="text-xl font-semibold">{{ number_format($summary->delivered, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Échecs</dt><dd class="text-xl font-semibold">{{ number_format($summary->failed, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Résultat inconnu</dt><dd class="text-xl font-semibold">{{ number_format($summary->unknown, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">En attente / en cours</dt><dd class="text-xl font-semibold">{{ number_format($summary->inProgress, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Bloqués avant envoi</dt><dd class="text-xl font-semibold">{{ number_format($summary->blocked, 0, ',', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Crédits consommés</dt><dd class="text-xl font-semibold">{{ \App\Domain\Credits\CreditUnits::format($summary->consumedUnits()) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">
                    « Envoyés » = acceptés par le fournisseur (envoyés + délivrés). « Délivrés » dépend des accusés de réception du fournisseur : un message envoyé sans accusé n'est pas compté comme délivré@if ($simulated) ; la simulation ne produit aucun accusé @endif.
                    Crédits consommés = débits − remboursements du grand livre ({{ \App\Domain\Credits\CreditUnits::format($summary->debitedUnits) }} débités, {{ \App\Domain\Credits\CreditUnits::format($summary->refundedUnits) }} remboursés).
                </p>
                @unless ($simulated)
                    <p class="mt-1 text-xs text-slate-600">
                        Taux de délivrance : {{ $summary->deliveryRate() ?? 'non calculable (aucun message envoyé)' }}
                        · Taux d'échec : {{ $summary->failureRate() ?? 'non calculable (aucun envoi tenté)' }}
                    </p>
                @endunless
            </section>

            <section class="rounded bg-white p-4 shadow" aria-labelledby="blocked-title">
                <h3 id="blocked-title" class="mb-3 font-medium">Messages bloqués avant envoi, par motif</h3>
                @if ($summary->blocked === 0)
                    <p class="text-sm text-slate-600">Aucun message bloqué sur cette période.</p>
                @else
                    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        @foreach ($blockReasons as $key => $label)
                            @if ($summary->blockedByReason[$key] > 0)
                                <div><dt class="text-slate-500">{{ $label }}</dt><dd class="text-xl font-semibold">{{ number_format($summary->blockedByReason[$key], 0, ',', ' ') }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                @endif
            </section>

            <section class="rounded bg-white p-4 shadow" aria-labelledby="daily-title">
                <h3 id="daily-title" class="mb-3 font-medium">Série journalière</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">Messages par jour ({{ config('app.timezone') }})</caption>
                        <thead class="border-b bg-slate-100">
                            <tr>
                                <th scope="col" class="px-3 py-2">Jour</th>
                                <th scope="col" class="px-3 py-2 text-right">Messages</th>
                                <th scope="col" class="px-3 py-2 text-right">Envoyés</th>
                                <th scope="col" class="px-3 py-2 text-right">Délivrés</th>
                                <th scope="col" class="px-3 py-2 text-right">Échecs</th>
                                <th scope="col" class="px-3 py-2 text-right">Inconnus</th>
                                <th scope="col" class="px-3 py-2 text-right">Bloqués</th>
                                <th scope="col" class="px-3 py-2 text-right">Crédits</th>
                                <th scope="col" class="px-3 py-2"><span class="sr-only">Proportion d'envoyés</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($daily as $d)
                            <tr class="border-b last:border-0" wire:key="d{{ $d['day'] }}">
                                <th scope="row" class="whitespace-nowrap px-3 py-2 font-normal">{{ \Carbon\Carbon::parse($d['day'])->format('d/m/Y') }}</th>
                                <td class="px-3 py-2 text-right">{{ $d['total'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $d['sent'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $d['delivered'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $d['failed'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $d['unknown'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $d['blocked'] }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ \App\Domain\Credits\CreditUnits::format($d['consumed_units']) }}</td>
                                <td class="w-32 px-3 py-2" aria-hidden="true"><div class="h-2 rounded bg-slate-100"><div class="h-2 rounded {{ $simulated ? 'bg-amber-600' : 'bg-red-700' }}" style="width: {{ round($d['sent'] * 100 / $maxDayTotal) }}%"></div></div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-slate-500">Seuls les jours avec au moins un message ou une écriture de crédits sont listés. La barre compare les messages envoyés du jour au jour le plus chargé de la période.</p>
            </section>
        @endif

        <section class="rounded bg-white p-4 shadow" aria-labelledby="export-title">
            <h3 id="export-title" class="mb-2 font-medium">Export CSV</h3>
            @if ($exportUrl)
                <p class="mb-2 text-sm text-slate-600">Messages de la période et du mode affichés ({{ $simulated ? 'simulation' : 'envois réels' }}), un par ligne : date, numéro, campagne, statut, motif de blocage, segments, coût en crédits (net, grand livre), simulé oui/non.
                    Fichier UTF-8 avec BOM, séparateur « {{ config('reports.csv_delimiter') }} », {{ number_format((int) config('reports.export_max_rows'), 0, ',', ' ') }} lignes maximum. Contient des numéros de téléphone : données personnelles, à manipuler avec précaution.</p>
                <a href="{{ $exportUrl }}" class="inline-block rounded border border-slate-300 px-4 py-2 font-medium">Télécharger le CSV</a>
            @else
                <p class="text-sm text-slate-600">L'export des messages contient des numéros de téléphone : il est réservé aux propriétaires et administrateurs de l'entreprise.</p>
            @endif
        </section>
    @endif
</div>
