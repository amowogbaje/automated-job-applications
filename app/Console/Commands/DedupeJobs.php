<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use App\Services\Dedupe\JobDeduper;
use App\Services\Dedupe\JobFingerprint;
use Illuminate\Console\Command;

class DedupeJobs extends Command
{
    protected $signature = 'jobs:dedupe {--merge : Actually hide duplicates (soft-merge). Without it, only reports what would be merged}';

    protected $description = 'Fingerprint listings stored before de-duplication existed and find the same job posted several times (same ATS id / apply link, same company + title, near-identical text). Duplicates are soft-merged (hidden, never deleted) into the earliest copy (native jobs always win), which records where else it appeared.';

    public function handle(JobDeduper $deduper): int
    {
        $fingerprinted = 0;
        JobListing::query()->whereNull('fingerprint')->whereNull('apply_key')->whereNull('simhash')->whereNull('canonical_id')
            ->chunkById(200, function ($rows) use ($deduper, &$fingerprinted) {
                foreach ($rows as $job) {
                    $deduper->refreshKeys($job);
                    $fingerprinted++;
                }
            });
        $this->info("Fingerprinted {$fingerprinted} existing listing(s).");

        $groups = [];
        JobListing::query()->whereNull('canonical_id')->where(fn ($q) => $q->whereNotNull('fingerprint')->orWhereNotNull('apply_key'))
            ->orderBy('id')->get()
            ->each(function (JobListing $j) use (&$groups) {
                foreach (array_filter(['f:' . $j->fingerprint, $j->apply_key ? 'a:' . $j->apply_key : null]) as $key) {
                    if ($key !== 'f:') {
                        $groups[$key][] = $j->id;
                    }
                }
            });

        $merged = 0;
        $handled = [];
        foreach ($groups as $ids) {
            $ids = array_values(array_unique(array_diff($ids, $handled)));
            if (count($ids) < 2) {
                continue;
            }

            $rows = JobListing::whereIn('id', $ids)->orderBy('id')->get();
            // Native jobs win, then the earliest copy.
            $keeper = $rows->first(fn ($j) => $j->isNative()) ?? $rows->first();

            foreach ($rows->where('id', '!=', $keeper->id) as $dup) {
                $this->line(($this->option('merge') ? 'Merging' : 'Would merge') . " #{$dup->id} ({$dup->source}: {$dup->title}) into #{$keeper->id} ({$keeper->source})");
                if ($this->option('merge')) {
                    $also = (array) ($keeper->also_posted ?? []);
                    $also[] = ['source' => $dup->source, 'platform' => $dup->social_platform, 'url' => $dup->url, 'posted_at' => $dup->posted_at?->toIso8601String()];
                    $keeper->also_posted = $also;
                    $dup->update(['canonical_id' => $keeper->id]);
                }
                $handled[] = $dup->id;
                $merged++;
            }
            if ($this->option('merge')) {
                $keeper->save();
            }
        }

        $this->info(($this->option('merge') ? 'Merged ' : 'Found ') . "{$merged} duplicate listing(s)." . ($this->option('merge') ? '' : ' Re-run with --merge to hide them.'));

        return self::SUCCESS;
    }
}
