<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use App\Services\Matching\JobMatchService;
use Illuminate\Console\Command;

class MatchJobs extends Command
{
    protected $signature = 'jobs:match {--user= : Only this user id} {--backfill : Forget stored matches and re-score the last ' . JobMatchService::WINDOW_DAYS . ' days}';

    protected $description = 'Score newly fetched jobs against every account\'s chosen skills and store the matches (feed + email alerts read these)';

    public function handle(JobMatchService $matcher): int
    {
        $userId = $this->option('user');

        if ($userId || $this->option('backfill')) {
            $profiles = CareerProfile::query()->when($userId, fn ($q) => $q->where('user_id', $userId))->get();
            $total = 0;
            foreach ($profiles as $profile) {
                $n = $this->option('backfill') ? $matcher->backfill($profile) : $matcher->matchNew($profile);
                $this->line("user {$profile->user_id}: {$n} match(es)");
                $total += $n;
            }
            $this->info("Done. {$total} match(es) stored.");

            return self::SUCCESS;
        }

        $r = $matcher->matchAll();
        $this->info("Done. Matched against {$r['profiles']} profile(s), {$r['matches']} new match(es) stored.");

        return self::SUCCESS;
    }
}
