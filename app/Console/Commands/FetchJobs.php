<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Services\JobSources\AdzunaSource;
use App\Services\JobSources\ArbeitnowSource;
use App\Services\JobSources\HimalayasSource;
use App\Services\JobSources\JobSourceInterface;
use App\Services\JobSources\RemoteOkSource;
use App\Services\JobSources\WeWorkRemotelySource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FetchJobs extends Command
{
    protected $signature = 'jobs:fetch {--source= : Only run one source, e.g. arbeitnow}';

    protected $description = 'Fetch job listings from all configured sources, dedup, and store them — filtering by skills/keywords now happens per-user on the dashboard, not here';

    /** @var JobSourceInterface[] */
    protected array $sources = [];

    // Ingestion no longer filters by any one user's required skills —
    // that's now a per-user call made live on /jobs (see CareerProfile).
    // This is only a broad backstop so a general job board like Adzuna
    // doesn't flood the table with completely unrelated postings before
    // anyone's even had a chance to see them. Deliberately generic, not
    // configurable — a real per-user filter belongs in a career profile,
    // not here.
    //
    // Kept intentionally specific rather than using bare "engineer" or
    // "architect" — those alone also match sales engineer, mechanical
    // engineer, sound engineer, building architect, etc. This site is
    // for software/dev roles specifically, not "tech industry" broadly.
    private const BROAD_TECH_BACKSTOP = [
        'developer', 'programmer', 'software engineer', 'backend engineer',
        'back-end engineer', 'frontend engineer', 'front-end engineer',
        'full-stack engineer', 'full stack engineer', 'software architect',
        'solutions architect', 'cloud architect', 'devops engineer',
        'platform engineer', 'site reliability engineer', 'sre',
        'qa engineer', 'test engineer', 'data engineer', 'ml engineer',
        'machine learning engineer', 'data scientist', 'sysadmin',
        'systems administrator', 'devops', 'backend', 'back-end', 'frontend',
        'front-end', 'full-stack', 'full stack',
    ];

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

        // Legacy columns kept for the CLI automation commands
        // (applications:generate / applications:process), which still
        // operate against a single "default" account's profile rather
        // than per-user — see the scope note in README. The web
        // dashboard itself never reads these; it scores live per viewer.
        $defaultProfile = CareerProfile::forUser();

        $totalNew = 0;
        $totalSeen = 0;
        $totalSkippedByBackstop = 0;

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

                if (! Str::contains($text, self::BROAD_TECH_BACKSTOP)) {
                    $totalSkippedByBackstop++;
                    continue;
                }

                [$applyMethod, $applyEmail] = $this->detectApplyMethod($listing['description'] ?? '');
                $result = $defaultProfile->score($listing['title'] ?? '', $listing['description'] ?? '');

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
                        'match_score' => $result['score'],
                        'matched_keywords' => $result['matched'],
                        'posted_at' => $listing['posted_at'],
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $totalNew++;
                }
            }
        }

        $this->info("Done. Saw {$totalSeen} listings, filtered out {$totalSkippedByBackstop} as clearly non-tech, stored {$totalNew} new listings.");
        $this->line('Per-user relevance (skills/keywords) is applied live on each account\'s /jobs page — nothing here decides that anymore.');

        return self::SUCCESS;
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
