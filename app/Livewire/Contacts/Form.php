<?php

namespace App\Livewire\Contacts;

use App\Domain\Contacts\PhoneNormalizer;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Contact')]
class Form extends Component
{
    #[Locked]
    public ?int $contactId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    /** @var array<int> */
    public array $groupIds = [];

    public function mount(?int $contact = null): void
    {
        if ($contact !== null) {
            $c = Contact::findOrFail($contact); // 404 si d'une autre entreprise
            $this->contactId = $c->id;
            $this->name = (string) $c->name;
            $this->phone = $c->phone;
            $this->email = (string) $c->email;
            $this->groupIds = $c->groups()->pluck('contact_groups.id')->all();
        }
    }

    public function save(PhoneNormalizer $phones, CurrentCompany $current)
    {
        $data = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'groupIds' => ['array'],
            'groupIds.*' => ['integer'],
        ]);

        $e164 = $phones->normalize($data['phone'], $current->company()->country);
        if ($e164 === null) {
            throw ValidationException::withMessages(['phone' => 'Numéro de mobile invalide pour le pays de l\'entreprise.']);
        }

        $duplicate = Contact::where('phone', $e164)->when($this->contactId, fn ($q) => $q->whereKeyNot($this->contactId))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['phone' => 'Ce numéro existe déjà dans vos contacts.']);
        }

        $attrs = ['name' => $data['name'] ?: null, 'phone' => $e164, 'email' => $data['email'] ?: null];
        $contact = $this->contactId ? tap(Contact::findOrFail($this->contactId))->update($attrs) : Contact::create($attrs);

        // Seuls les groupes de l'entreprise active sont retenus (requête scopée).
        $contact->groups()->sync(ContactGroup::whereIn('id', $data['groupIds'])->pluck('id')->mapWithKeys(
            fn ($id) => [$id => ['company_id' => $contact->company_id]]
        )->all());

        session()->flash('status', 'Contact enregistré.');

        return $this->redirectRoute('contacts.index', navigate: false);
    }

    public function render()
    {
        return view('livewire.contacts.form', ['groups' => ContactGroup::orderBy('name')->get()]);
    }
}
