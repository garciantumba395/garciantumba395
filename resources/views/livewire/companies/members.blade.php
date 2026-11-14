<div>
    <h2 class="mb-4 text-xl font-semibold">Membres</h2>
    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-100"><tr><th class="px-3 py-2">Nom</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">Rôle</th></tr></thead>
            <tbody>
            @foreach ($members as $m)
                <tr class="border-b last:border-0">
                    <td class="px-3 py-2">{{ $m->name }}</td>
                    <td class="px-3 py-2">{{ $m->email }}</td>
                    <td class="px-3 py-2">{{ \App\Enums\CompanyRole::from($m->pivot->role)->label() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-slate-500">L'invitation de nouveaux membres sera ajoutée dans une étape ultérieure.</p>
</div>
