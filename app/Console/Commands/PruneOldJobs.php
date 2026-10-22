<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use Illuminate\Console\Command;

class PruneOldJobs extends Command
{
    protected $signature = 'jobs:prune {--days= : Delete listings posted before this many days ago (default: the 30-day cap)}';

    protected $description = 'Delete old job listings to keep the dashboard/table small';

    public function handle(): int
    {
        $days = min((int) config('jobs.max_age_days', 30), (int) ($this->option('days') ?: config('jobs.max_age_days', 30)));
        $cutoff = now()->subDays($days);

        // Take any soft-merged duplicates down with their keeper so nothing is left hidden behind a deleted listing.
        $ids = JobListing::query()
            ->where('source', '!=', 'native')
            ->where('posted_at', '<', $cutoff)
            ->pluck('id');

        $deleted = JobListing::query()->where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('canonical_id', $ids))->delete();

        $this->info("Pruned {$deleted} listing(s) posted before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
