<?php

namespace App\Console\Commands;

use App\Services\Agent\Leads\LeadSequenceRunner;
use Illuminate\Console\Command;

class LeadsFollowUp extends Command
{
    protected $signature = 'leads:follow-up';

    protected $description = 'Send due follow-up emails (if automatic sending is on), notify you about phone follow-ups, and close leads that never replied';

    public function handle(LeadSequenceRunner $runner): int
    {
        $r = $runner->run();
        $this->info("Sequences processed: {$r['processed']}, notifications: {$r['notifications']}");

        return self::SUCCESS;
    }
}
