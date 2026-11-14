<div>
    @if ($companies->count() > 1)
        <select aria-label="Changer d'entreprise" class="rounded border border-slate-300 px-2 py-1"
                wire:change="switch($event.target.value)">
            @foreach ($companies as $c)
                <option value="{{ $c->id }}" @selected($c->id === $currentId)>{{ $c->name }}</option>
            @endforeach
        </select>
    @else
        <span class="text-slate-500">{{ $companies->first()?->name }}</span>
    @endif
</div>
