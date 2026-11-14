<?php

namespace App\Livewire\Messages;

use App\Domain\Legal\MessageComposer;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsSendingDisabledException;
use App\Domain\Sms\SmsStatus;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Support\Security\Throttle;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Messages')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public ?int $contactId = null;

    public string $body = '';

    /** Clé d'idempotence de la tentative en cours : un double clic renvoie le même message, pas deux. */
    #[Locked]
    public string $attemptKey = '';

    public function mount(): void
    {
        $this->attemptKey = (string) Str::uuid();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function sendTest(SmsDispatcher $dispatcher, CurrentCompany $current, MessageComposer $composer, SmsManager $manager): void
    {
        abort_unless($current->role()?->canManageCompany(), 403);
        if (! Throttle::attempt('test_send', Auth::id())) {
            $this->addError('body', Throttle::message('test_send', Auth::id()));

            return;
        }
        $this->validate(['contactId' => ['required', 'integer'], 'body' => ['required', 'string', 'max:'.config('sms.max_body_length')]]);

        $contact = Contact::findOrFail($this->contactId); // scopé : 404 hors entreprise

        $company = $current->company();
        if (config('legal.enforce') && $manager->isLiveMode() && ! $company->hasCompleteLegalIdentity()) {
            $this->addError('body', 'Identité légale de l\'expéditeur incomplète (art. 66) : complétez-la dans Paramètres avant tout envoi réel.');

            return;
        }

        try {
            $message = $dispatcher->dispatch($contact, $composer->compose($this->body, $contact, $company), 'test:'.$this->attemptKey, null, Auth::id());
        } catch (SmsSendingDisabledException|InvalidArgumentException $e) {
            $this->addError('body', $e->getMessage());

            return;
        }

        $this->attemptKey = (string) Str::uuid();
        $this->reset('body');
        session()->flash('status', 'Message traité : '.$message->status->label().($message->simulated ? ' (simulation, aucun SMS réel envoyé).' : '.'));
    }

    public function render(CurrentCompany $current, SmsManager $manager)
    {
        $query = SmsMessage::query()->latest('id');
        if (SmsStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        return view('livewire.messages.index', [
            'messages' => $query->paginate(25),
            'contacts' => Contact::orderBy('name')->orderBy('id')->limit(200)->get(),
            'statuses' => SmsStatus::cases(),
            'live' => $manager->isLiveMode(),
            'sendingStatus' => $current->company()->fresh()->sending_status,
            'sendingReason' => $current->company()->fresh()->sending_status_reason,
            'canTest' => (bool) $current->role()?->canManageCompany(),
        ]);
    }
}
