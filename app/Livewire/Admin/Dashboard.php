<?php

namespace App\Livewire\Admin;

use App\Domain\Campaigns\CampaignStatus;
use App\Livewire\Admin\Concerns\AuthorizesPlatformAdmin;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Administration — tableau de bord')]
class Dashboard extends Component
{
    use AuthorizesPlatformAdmin;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render()
    {
        $this->authorizeAdmin();

        // Uniquement des AGRÉGATS (comptages/sommes) issus de la base, jamais de contenu d'entreprise.
        $messages = SmsMessage::withoutGlobalScopes()->selectRaw('simulated, count(*) as n')->groupBy('simulated')->pluck('n', 'simulated');

        return view('livewire.admin.dashboard', [
            'companies' => Company::count(),
            'companiesActive' => Company::where('is_active', true)->count(),
            'users' => User::count(),
            'usersDisabled' => User::whereNotNull('disabled_at')->count(),
            'messagesReal' => (int) ($messages[0] ?? $messages[false] ?? 0),
            'messagesSimulated' => (int) ($messages[1] ?? $messages[true] ?? 0),
            'creditUnits' => (int) Company::sum('credit_balance_units'),
            'campaignsActive' => Campaign::withoutGlobalScopes()->whereIn('status', [CampaignStatus::Scheduled->value, CampaignStatus::Preparing->value, CampaignStatus::Sending->value])->count(),
            'campaignsPaused' => Campaign::withoutGlobalScopes()->where('status', CampaignStatus::Paused->value)->count(),
            'failedJobs' => (int) DB::table(config('queue.failed.table', 'failed_jobs'))->count(),
        ]);
    }
}
