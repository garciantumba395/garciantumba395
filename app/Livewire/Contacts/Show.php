<?php

namespace App\Livewire\Contacts;

use App\Domain\Consent\ConsentService;
use App\Domain\Consent\Eligibility;
use App\Domain\Consent\SendEligibility;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Suppression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Fiche contact')]
class Show extends Component
{
    #[Locked]
    public int $contactId;

    public string $source = 'web_form';

    public string $evidence = '';

    public string $obtainedAt = '';

    public function mount(int $contact): void
    {
        $this->contactId = Contact::findOrFail($contact)->id; // 404 si d'une autre entreprise
    }

    public function recordConsent(ConsentService $service): void
    {
        $this->validate([
            'source' => ['required', 'in:'.implode(',', array_keys(config('consent.sources')))],
            'evidence' => [config('consent.require_evidence') ? 'required' : 'nullable', 'string', 'max:2000'],
            'obtainedAt' => ['required', 'date', 'before_or_equal:today'],
        ]);

        try {
            $service->record(Contact::findOrFail($this->contactId), $this->source, $this->evidence ?: null, Carbon::parse($this->obtainedAt)->startOfDay(), Auth::id());
        } catch (InvalidArgumentException $e) {
            $this->addError('evidence', $e->getMessage());

            return;
        }
        $this->reset('evidence', 'obtainedAt');
        session()->flash('status', 'Consentement enregistré.');
    }

    public function revoke(int $consentId, ConsentService $service): void
    {
        $consent = Consent::where('contact_id', $this->contactId)->findOrFail($consentId);
        $service->revoke($consent, 'Retrait manuel', Auth::id());
        session()->flash('status', 'Consentement révoqué.');
    }

    public function optOut(ConsentService $service): void
    {
        $service->optOut(Contact::findOrFail($this->contactId)->phone, 'manual', 'Désinscription depuis la fiche contact', Auth::id());
        session()->flash('status', 'Contact désinscrit : aucun envoi ne sera possible.');
    }

    public function render(SendEligibility $eligibility)
    {
        $contact = Contact::findOrFail($this->contactId);

        return view('livewire.contacts.show', [
            'contact' => $contact,
            'consents' => $contact->consents()->latest('obtained_at')->latest('id')->get(),
            'eligibility' => $eligibility->for($contact),
            'suppression' => Suppression::active()->where('phone', $contact->phone)->first(),
            'sources' => config('consent.sources'),
            'Eligible' => Eligibility::Eligible,
        ]);
    }
}
