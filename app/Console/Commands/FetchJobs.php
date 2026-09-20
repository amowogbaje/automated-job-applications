<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use App\Services\JobSources\AdzunaSource;
use App\Services\JobSources\ArbeitnowSource;
use App\Services\JobSources\HimalayasSource;
use App\Services\JobSources\JobSourceInterface;
use App\Services\JobSources\RemoteOkSource;
use App\Services\JobSources\WeWorkRemotelySource;
use Illuminate\Console\Command;

class FetchJobs extends Command
{
    protected $signature = 'jobs:fetch {--source= : Only run one source, e.g. arbeitnow}';

    protected $description = 'Fetch job listings from all configured sources, dedup, score, and store them';

    /** @var JobSourceInterface[] */
    protected array $sources = [];

    public function __construct()
    {
        parent::__construct();

        $this->sources = [
            new ArbeitnowSource(),
            new RemoteOkSource(),
            new WeWorkRemotelySource(),
            new AdzunaSource(),
            new HimalayasSource(),
        ];
    }

    public function handle(): int
    {
        $only = $this->option('source');

        // "Nice to have" keywords — each match adds to the score but isn't required.
        $keywords = $this->splitConfig('services.job_aggregator.keywords', 'laravel,php,full-stack');

        // "Must have" skills — a listing needs at least min_required_matches of these
        // to be stored at all. This is what actually keeps unrelated jobs out.
        $requiredSkills = $this->splitConfig('services.job_aggregator.required_skills', '');
        $minRequiredMatches = (int) config('services.job_aggregator.min_required_matches', 1);

        // Any hit here drops the listing outright, regardless of other matches.
        $excluded = $this->splitConfig('services.job_aggregator.excluded_keywords', '');

        $totalNew = 0;
        $totalSeen = 0;
        $totalSkippedByFilter = 0;

        foreach ($this->sources as $source) {
            if ($only && $source->name() !== $only) {
                continue;
            }

            $this->info("Fetching from {$source->name()}...");
            $listings = $source->fetch();
            $totalSeen += count($listings);

            foreach ($listings as $listing) {
                $hash = sha1(($listing['url'] ?? '') . ($listing['title'] ?? '') . ($listing['company'] ?? ''));
                $text = strtolower(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''));

                if ($excluded->isNotEmpty() && $excluded->contains(fn ($k) => str_contains($text, $k))) {
                    $totalSkippedByFilter++;
                    continue;
                }

                $requiredHits = $requiredSkills->filter(fn ($k) => str_contains($text, $k))->values();

                if ($requiredSkills->isNotEmpty() && $requiredHits->count() < $minRequiredMatches) {
                    $totalSkippedByFilter++;
                    continue;
                }

                $niceToHaveHits = $keywords->filter(fn ($k) => str_contains($text, $k))->values();
                $matched = $requiredHits->merge($niceToHaveHits)->unique()->values();

                // If you haven't configured required_skills at all, fall back to the
                // old behavior of needing at least one "nice to have" keyword.
                if ($requiredSkills->isEmpty() && $keywords->isNotEmpty() && $matched->isEmpty()) {
                    $totalSkippedByFilter++;
                    continue;
                }

                [$applyMethod, $applyEmail] = $this->detectApplyMethod($listing['description'] ?? '');

                $created = JobListing::firstOrCreate(
                    ['url_hash' => $hash],
                    [
                        'source' => $listing['source'],
                        'external_id' => $listing['external_id'] ?? null,
                        'title' => $listing['title'],
                        'company' => $listing['company'] ?? null,
                        'location' => $listing['location'] ?? null,
                        'is_remote' => $listing['is_remote'] ?? false,
                        'description' => \Illuminate\Support\Str::limit($listing['description'] ?? '', 2000),
                        'url' => $listing['url'],
                        'apply_method' => $applyMethod,
                        'apply_email' => $applyEmail,
                        'match_score' => $requiredHits->count() * 2 + $niceToHaveHits->count(),
                        'matched_keywords' => $matched->all(),
                        'posted_at' => $listing['posted_at'],
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $totalNew++;
                }
            }
        }

        $this->info("Done. Saw {$totalSeen} listings, filtered out {$totalSkippedByFilter}, stored {$totalNew} new matches.");

        return self::SUCCESS;
    }

    protected function splitConfig(string $key, string $default)
    {
        return collect(explode(',', config($key, $default)))
            ->map(fn ($k) => trim(strtolower($k)))
            ->filter()
            ->values();
    }

    /**
     * Look for an explicit "email your application/resume/CV to X" instruction
     * in the job description. Deliberately conservative: a bare email address
     * mentioned in passing (e.g. a general contact email) is NOT enough — we
     * only auto-send when the text clearly asks candidates to apply by email.
     * Anything we're not confident about falls through to "web" and gets
     * bundled into the digest instead, where you make the call yourself.
     *
     * @return array{0: string, 1: ?string} [apply_method, apply_email]
     */
    protected function detectApplyMethod(string $description): array
    {
        if (! preg_match('/[a-zA-Z0-9.+_-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+/i', $description, $emailMatch)) {
            return ['web', null];
        }

        $email = strtolower($emailMatch[0]);

        $applyPhrase = '/\b(email|send|forward)\b[^.]{0,60}\b(resume|cv|application|cover letter)\b'
            . '|\b(apply|send your (resume|cv|application))[^.]{0,40}\bto\b[^.]{0,20}@/i';

        if (preg_match($applyPhrase, $description)) {
            return ['email', $email];
        }

        return ['web', null];
    }
}
