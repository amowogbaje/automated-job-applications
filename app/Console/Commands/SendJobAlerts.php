<?php

namespace App\Console\Commands;

use App\Services\Matching\JobAlertService;
use Illuminate\Console\Command;

class SendJobAlerts extends Command
{
    protected $signature = 'jobs:alerts';

    protected $description = 'Email each opted-in account the new jobs that match the skills they chose (each job once)';

    public function handle(JobAlertService $alerts): int
    {
        $this->info('Sent ' . $alerts->sendDue() . ' alert email(s).');

        return self::SUCCESS;
    }
}
