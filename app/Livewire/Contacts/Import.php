<?php

namespace App\Livewire\Contacts;

use App\Domain\Contacts\ContactImporter;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Importer des contacts')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    public ?int $groupId = null;

    public ?int $resultId = null;

    public function import(ContactImporter $importer): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'groupId' => ['nullable', 'integer'],
        ]);

        try {
            $result = $importer->import($this->file->getRealPath(), $this->file->getClientOriginalName(), $this->groupId, Auth::id());
        } catch (InvalidArgumentException $e) {
            $this->addError('file', $e->getMessage());

            return;
        } finally {
            $this->file->delete(); // le fichier n'est pas conservé
        }

        $this->resultId = $result->id;
        $this->reset('file');
    }

    public function render()
    {
        return view('livewire.contacts.import', [
            'groups' => ContactGroup::orderBy('name')->get(),
            'result' => $this->resultId ? ContactImport::find($this->resultId) : null,
            'history' => ContactImport::latest()->limit(10)->get(),
        ]);
    }
}
