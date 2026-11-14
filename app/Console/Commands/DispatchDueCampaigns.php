<?php

namespace App\Console\Commands;

use App\Domain\Campaigns\CampaignService;
use Illuminate\Console\Command;

class DispatchDueCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Démarre les campagnes programmées arrivées à échéance et reprend celles bloquées par la limite journalière';

    public function handle(CampaignService $service): int
    {
        $this->info($service->dispatchDue().' campagne(s) démarrée(s), '.$service->resumeDailyLimited().' reprise(s) automatique(s).');

        return self::SUCCESS;
    }
}
