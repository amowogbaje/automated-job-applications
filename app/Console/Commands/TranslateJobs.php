<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Services\Translation\LanguageDetector;
use App\Services\Translation\TranslationService;
use Illuminate\Console\Command;

class TranslateJobs extends Command
{
    protected $signature = 'jobs:translate {--limit= : Max listings this run (default from config)} {--backfill : First detect the language of existing listings that have none} {--status : Show each driver, its remaining quota, and whether it is paused, then exit}';

    protected $description = 'Translate non-English listings once and store the result. Runs right after jobs:fetch and every 5 minutes; only listings that match at least one account\'s profile are translated, to protect free-tier quotas. The "Show translation" button only reads what this stores.';

    public function handle(TranslationService $translator, LanguageDetector $detector): int
    {
        if ($this->option('status')) {
            $rows = [];
            foreach ($translator->status() as $name => $s) {
                $rows[] = [$name, is_int($s['remaining']) ? number_format($s['remaining']) . ' chars' : $s['remaining'], $s['paused_until'] ?? 'no'];
            }
            $this->table(['Driver (in order)', 'Remaining budget', 'Paused until'], $rows ?: [['none configured', '-', '-']]);

            return self::SUCCESS;
        }

        if ($this->option('backfill')) {
            $this->backfill($detector);
        }

        if (! $translator->hasDrivers()) {
            $this->warn('No translation driver is configured (set DEEPL_API_KEY, LIBRETRANSLATE_URL, or leave MyMemory/ai in TRANSLATE_DRIVERS).');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('translation.per_run', 15));
        $profiles = CareerProfile::query()->get();
        if ($profiles->isEmpty()) {
            return self::SUCCESS;
        }

        $pending = JobListing::query()
            ->where('translation_status', 'pending')
            ->where('translation_attempts', '<', (int) config('translation.max_attempts', 3))
            ->where('posted_at', '>=', now()->subDays(7))
            ->orderByDesc('posted_at')
            ->limit(300) // scan window; only matches are translated
            ->get();

        $done = 0;
        foreach ($pending as $job) {
            if ($done >= $limit) {
                break;
            }

            // Judge relevance on the ORIGINAL text; skill names are mostly language-neutral.
            $relevant = $profiles->contains(fn (CareerProfile $p) => $p->score($job->title, $job->description)['matches']);
            if (! $relevant) {
                continue; // stays pending; picked up later if someone's profile starts matching
            }

            $result = $translator->translateListing($job);
            $job->increment('translation_attempts');

            if ($result === null) {
                $this->warn("  Could not translate #{$job->id} ({$job->title}), will retry.");
                if ($job->translation_attempts >= (int) config('translation.max_attempts', 3)) {
                    $job->update(['translation_status' => 'failed']);
                }
                continue;
            }

            $job->update([
                'title_translated' => $result['title'],
                'description_translated' => $result['description'],
                'translation_provider' => $result['provider'],
                'translation_status' => 'done',
                'translated_at' => now(),
            ]);
            $done++;
            $this->line("  Translated #{$job->id} via {$result['provider']}: {$result['title']}");
        }

        $this->info("Translated {$done} listing(s).");

        return self::SUCCESS;
    }

    private function backfill(LanguageDetector $detector): void
    {
        $n = 0;
        JobListing::query()->whereNull('language')->where('kind', 'job')->where('posted_at', '>=', now()->subDays(7))
            ->chunkById(200, function ($rows) use ($detector, &$n) {
                foreach ($rows as $job) {
                    $lang = $detector->detect($job->title . '. ' . $job->description);
                    $job->update(['language' => $lang, 'translation_status' => $lang === 'en' ? 'none' : 'pending']);
                    $n++;
                }
            });
        $this->info("Detected language for {$n} existing listing(s).");
    }
}
