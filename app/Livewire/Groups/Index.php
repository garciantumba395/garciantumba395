<?php

namespace App\Livewire\Groups;

use App\Models\ContactGroup;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groupes')]
class Index extends Component
{
    public string $name = '';

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255', Rule::unique('contact_groups', 'name')->where('company_id', app(CurrentCompany::class)->id())]]);
        ContactGroup::create(['name' => $this->name]);
        $this->reset('name');
    }

    public function delete(int $id): void
    {
        ContactGroup::findOrFail($id)->delete(); // les contacts restent ; seul le lien est supprimé
        session()->flash('status', 'Groupe supprimé (les contacts sont conservés).');
    }

    public function render()
    {
        return view('livewire.groups.index', ['groups' => ContactGroup::withCount('contacts')->orderBy('name')->get()]);
    }
}
