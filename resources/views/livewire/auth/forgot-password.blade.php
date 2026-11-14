<div>
    @if ($sent)
        <p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé.</p>
    @else
        <form wire:submit="sendLink" class="space-y-4">
            <div>
                <label for="email" class="text-sm font-medium">Adresse e-mail</label>
                <input id="email" type="email" wire:model="email" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Envoyer le lien</button>
        </form>
    @endif
    <p class="mt-4 text-sm"><a href="{{ route('login') }}" class="underline">Retour à la connexion</a></p>
</div>
