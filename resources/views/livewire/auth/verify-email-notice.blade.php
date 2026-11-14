<div class="space-y-4">
    <h2 class="text-lg font-semibold">Vérifiez votre adresse e-mail</h2>
    <p class="text-sm">Un lien de vérification a été envoyé à <strong>{{ $email }}</strong>. Cliquez dessus pour activer votre compte. Tant que l'adresse n'est pas vérifiée, aucune autre page n'est accessible.</p>
    @if ($sent)
        <p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">Un nouveau lien vient d'être envoyé.</p>
    @endif
    @error('resend') <p class="rounded bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="resend" wire:loading.attr="disabled" class="rounded bg-slate-900 px-4 py-2 font-medium text-white">Renvoyer le lien</button>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded border border-slate-300 px-4 py-2">Se déconnecter</button></form>
    </div>
    <p class="text-xs text-slate-500">Vous n'avez rien reçu ? Vérifiez vos courriers indésirables, ou contactez l'administrateur de la plateforme.</p>
</div>
