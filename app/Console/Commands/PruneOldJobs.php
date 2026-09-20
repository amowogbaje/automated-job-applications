<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use Illuminate\Console\Command;

class PruneOldJobs extends Command
{
    protected $signature = 'jobs:prune {--days=14 : Delete listings posted before this many days ago}';

    protected $description = 'Delete old job listings to keep the dashboard/table small — skips anything with a pending (undiscarded) application draft';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = JobListing::query()
            ->where('posted_at', '<', $cutoff)
            ->whereDoesntHave('applicationDraft', fn ($q) => $q->whereIn('status', ['draft', 'ready']))
            ->delete();

        $this->info("Pruned {$deleted} listing(s) posted before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
