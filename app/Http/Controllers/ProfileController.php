<?php

namespace App\Http\Controllers;

use App\Models\CareerProfile;
use App\Models\Resume;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /profile — LinkedIn-style profile + the matching/automation settings, in tabs.
    public function edit(Request $request)
    {
        $resume = Resume::current();
        $profile = CareerProfile::forUser($request->user()->id);

        $tab = $request->query('tab', 'profile');
        $tab = in_array($tab, ['profile', 'matching', 'autoapply', 'notifications'], true) ? $tab : 'profile';

        return view('profile.edit', [
            'profile' => $profile,
            'resume' => $resume,
            'strength' => $this->strength($resume),
            'tab' => $tab,
            'categoryLabels' => self::CATEGORY_LABELS,
        ]);
    }

    private const CATEGORY_LABELS = [
        'backend' => 'Backend', 'frontend' => 'Frontend', 'database' => 'Databases',
        'apis_integrations' => 'APIs & integrations', 'applied_ai' => 'Applied AI',
        'testing_devops' => 'Testing, tooling & DevOps', 'architecture' => 'Architecture & practices', 'other' => 'Other',
    ];

    /** @return array{percent:int, next:?string, items:array<int,array{label:string,done:bool}>} */
    private function strength(?Resume $resume): array
    {
        $checks = [
            ['Upload or import a resume', (bool) $resume],
            ['Add a headline', (bool) $resume?->headline],
            ['Write a summary', strlen((string) $resume?->summary) >= 80],
            ['Add your location', (bool) $resume?->location],
            ['Link GitHub, LinkedIn or your site', (bool) ($resume?->github_url || $resume?->linkedin_url || $resume?->website_url)],
            ['Add work experience', (bool) $resume?->experiences->count()],
            ['Add education', (bool) $resume?->education->count()],
            ['List at least 8 skills', ($resume?->skills->count() ?? 0) >= 8],
            ['Showcase a project', (bool) $resume?->projects->count()],
        ];

        $done = count(array_filter($checks, fn ($c) => $c[1]));
        $next = collect($checks)->first(fn ($c) => ! $c[1]);

        return [
            'percent' => (int) round($done / count($checks) * 100),
            'next' => $next[0] ?? null,
            'items' => array_map(fn ($c) => ['label' => $c[0], 'done' => $c[1]], $checks),
        ];
    }

    // PATCH /profile — matching rules only (so saving it never rewrites email/auto-apply settings).
    public function update(Request $request)
    {
        $data = $request->validate([
            'keywords' => ['nullable', 'string', 'max:2000'],
            'required_skills' => ['nullable', 'string', 'max:2000'],
            'excluded_keywords' => ['nullable', 'string', 'max:2000'],
            'min_required_matches' => ['required', 'integer', 'min:1', 'max:20'],
            'hide_region_restricted' => ['nullable', 'boolean'],
        ]);

        $normalizer = app(SkillNormalizer::class);

        CareerProfile::forUser($request->user()->id)->update([
            // Typed skills go through the canonical catalog: "nest js, graphql,
            // microservices" is saved as NestJS, GraphQL, Microservices.
            'keywords' => $normalizer->names($data['keywords'] ?? ''),
            'required_skills' => $normalizer->names($data['required_skills'] ?? ''),
            'excluded_keywords' => $this->splitCsv($data['excluded_keywords'] ?? ''),
            'min_required_matches' => $data['min_required_matches'],
            'hide_region_restricted' => $request->boolean('hide_region_restricted'),
        ]);

        return redirect()->route('profile.edit', ['tab' => 'matching'])->with('status', 'Matching rules saved. Your feed uses them from now on.');
    }

    // PATCH /profile/notifications
    public function updateNotifications(Request $request)
    {
        $data = $request->validate([
            'notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        CareerProfile::forUser($request->user()->id)->update([
            'notification_email' => $data['notification_email'] ?? null,
            'auto_send_enabled' => $request->boolean('auto_send_enabled'),
        ]);

        return redirect()->route('profile.edit', ['tab' => 'notifications'])->with('status', 'Notification settings saved.');
    }

    // PATCH /profile/social
    public function updateSocial(Request $request)
    {
        CareerProfile::forUser($request->user()->id)->update([
            'social_jobs_enabled' => $request->boolean('social_jobs_enabled'),
            'social_auto_email' => $request->boolean('social_auto_email'),
        ]);

        return redirect()->route('profile.edit', ['tab' => 'autoapply'])->with('status', 'Social job settings saved.');
    }

    // PATCH /profile/open-to-work
    public function toggleOpenToWork(Request $request)
    {
        $profile = CareerProfile::forUser($request->user()->id);
        $profile->update(['open_to_work' => ! $profile->open_to_work]);

        return back();
    }

    // POST /profile/test-match — paste a job, see exactly how YOUR rules score it.
    public function testMatch(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
        ]);

        $r = CareerProfile::forUser($request->user()->id)->score($data['title'], $data['description'] ?? '');

        $reason = match (true) {
            $r['matches'] => null,
            $r['excluded_by'] !== null => 'Dropped by your exclusion rule: "' . $r['excluded_by'] . '".',
            default => 'Not enough of your required core skills appear in this text (broad skills like Git or REST never qualify a job on their own).',
        };

        return response()->json($r + ['reason' => $reason]);
    }

    // PATCH /profile/auto-apply — its own form/route so saving it never
    // rewrites the matching or email settings (and vice versa).
    public function updateAutoApply(Request $request)
    {
        $data = $request->validate([
            'auto_apply_mode' => ['required', 'in:off,review,auto'],
            'daily_apply_cap' => ['required', 'integer', 'min:1', 'max:25'],
            'ats_boards' => ['nullable', 'string', 'max:2000'],
            'screening' => ['nullable', 'array'],
            'screening.*' => ['nullable', 'string', 'max:500'],
        ]);

        // "greenhouse:stripe, lever:spotify" -> ["greenhouse:stripe", "lever:spotify"], vendor must be one we can read.
        $boards = collect($this->splitCsv($data['ats_boards'] ?? ''))
            ->filter(fn ($b) => preg_match('/^(greenhouse|lever|ashby):[a-z0-9._-]+$/', $b))
            ->unique()->values()->all();

        CareerProfile::forUser($request->user()->id)->update([
            'auto_apply_mode' => $data['auto_apply_mode'],
            'daily_apply_cap' => $data['daily_apply_cap'],
            'ats_boards' => $boards,
            // Only answers you actually filled in; blanks mean "ask me" (needs_input).
            'screening_answers' => array_filter($data['screening'] ?? [], fn ($v) => filled($v)),
        ]);

        return redirect()->route('profile.edit', ['tab' => 'autoapply'])->with('status', 'Auto-apply settings saved.');
    }

    // POST /profile/from-resume — pulls every skill off your active resume
    // into both "keywords" and "required skills". Doesn't touch excluded
    // keywords or the match threshold — those aren't derivable from a resume.
    public function populateFromResume(Request $request)
    {
        $resume = Resume::current();

        if (! $resume || $resume->skills->isEmpty()) {
            return redirect()->route('profile.edit', ['tab' => 'matching'])->withErrors(['profile' => 'No active resume with skills to pull from. Import one first at /resume/upload.']);
        }

        $profile = CareerProfile::forUser($request->user()->id);
        $profile->fillFromResume($resume);
        $profile->save();

        return redirect()->route('profile.edit', ['tab' => 'matching'])->with('status', 'Imported ' . count($profile->keywords) . ' skills from your resume. Your core stack (' . count($profile->required_skills) . ') is required; broad skills like Git or REST are keywords only.');
    }

    private function splitCsv(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($v) => trim(strtolower($v)))
            ->filter()
            ->values()
            ->all();
    }
}
