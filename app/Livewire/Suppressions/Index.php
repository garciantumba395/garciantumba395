<?php

namespace App\Livewire\Suppressions;

use App\Domain\Consent\ConsentService;
use App\Domain\Contacts\PhoneNormalizer;
use App\Models\Suppression;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Désinscriptions')]
class Index extends Component
{
    use WithPagination;

    public string $numbers = '';

    public string $reason = '';

    /** @var array<int,string> motifs de levée saisis par ligne */
    public array $liftReasons = [];

    public function add(ConsentService $service, PhoneNormalizer $phones, CurrentCompany $current): void
    {
        $this->validate(['numbers' => ['required', 'string', 'max:20000'], 'reason' => ['nullable', 'string', 'max:255']]);

        $done = 0;
        $invalid = 0;
        foreach (preg_split('/[\s,;]+/', $this->numbers, -1, PREG_SPLIT_NO_EMPTY) as $raw) {
            $e164 = $phones->normalize($raw, $current->company()->country);
            if ($e164 === null) {
                $invalid++;

                continue;
            }
            $service->optOut($e164, 'manual', $this->reason ?: null, Auth::id());
            $done++;
        }

        $this->reset('numbers', 'reason');
        session()->flash('status', "$done numéro(s) désinscrit(s)".($invalid ? ", $invalid ligne(s) ignorée(s) (numéro invalide)." : '.'));
    }

    public function lift(int $id, ConsentService $service, CurrentCompany $current): void
    {
        // Levée réservée aux owner/admin ; vérifiée à chaque action (l'état Livewire vient du client).
        abort_unless($current->role()?->canManageCompany(), 403);

        $this->resetErrorBag();
        $suppression = Suppression::active()->findOrFail($id);
        try {
            $service->lift($suppression, $this->liftReasons[$id] ?? '', Auth::id());
        } catch (InvalidArgumentException $e) {
            $this->addError("liftReasons.$id", $e->getMessage());

            return;
        }
        session()->flash('status', 'Désinscription levée. Un nouveau consentement est requis avant tout envoi.');
    }

    public function render(CurrentCompany $current)
    {
        return view('livewire.suppressions.index', [
            'suppressions' => Suppression::active()->latest()->latest('id')->paginate(25),
            'canLift' => (bool) $current->role()?->canManageCompany(),
        ]);
    }
}
