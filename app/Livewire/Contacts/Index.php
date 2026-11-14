<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\ContactGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Contacts')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $group = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGroup(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        // Requête scopée : l'ID d'un contact d'une autre entreprise ne correspond à rien.
        Contact::findOrFail($id)->delete();
        session()->flash('status', 'Contact supprimé.');
    }

    public function render()
    {
        $query = Contact::query()->orderBy('name')->orderBy('id');

        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($this->search)).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])
                ->orWhere('phone', 'like', $term)
                ->orWhereRaw('lower(email) like ?', [$term]));
        }
        if ($this->group) {
            $query->whereHas('groups', fn ($q) => $q->whereKey($this->group));
        }

        return view('livewire.contacts.index', [
            'contacts' => $query->paginate(25),
            'groups' => ContactGroup::orderBy('name')->get(),
            'total' => Contact::count(),
        ]);
    }
}
