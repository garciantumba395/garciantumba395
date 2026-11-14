<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AccountService;
use App\Livewire\Admin\Concerns\AuthorizesPlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Administration — utilisateurs')]
class Users extends Component
{
    use AuthorizesPlatformAdmin, WithPagination;

    public string $search = '';

    /** @var array<int,string> motifs saisis par ligne */
    public array $reasons = [];

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function disable(int $id, AccountService $accounts): void
    {
        $this->authorizeAdmin();
        $this->change($id, fn (User $u) => $accounts->disableUser($u, Auth::user(), $this->reasons[$id] ?? ''), 'Compte désactivé : connexion refusée et session invalidée.');
    }

    public function enable(int $id, AccountService $accounts): void
    {
        $this->authorizeAdmin();
        $this->change($id, fn (User $u) => $accounts->enableUser($u, $this->reasons[$id] ?? ''), 'Compte réactivé.');
    }

    public function render()
    {
        $this->authorizeAdmin();
        $term = mb_strtolower(str_replace(['%', '_'], '', trim($this->search)));

        return view('livewire.admin.users', [
            'users' => User::withCount('companies')
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', ["%$term%"])->orWhereRaw('lower(email) like ?', ["%$term%"])))
                ->orderBy('name')->orderBy('id')->paginate(25),
        ]);
    }

    private function change(int $id, callable $fn, string $success): void
    {
        $this->resetErrorBag();
        $user = User::findOrFail($id);
        try {
            $fn($user);
        } catch (InvalidArgumentException $e) {
            $this->addError("reasons.$id", $e->getMessage());

            return;
        }
        unset($this->reasons[$id]);
        session()->flash('status', $success);
    }
}
