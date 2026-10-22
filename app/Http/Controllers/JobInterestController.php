<?php

namespace App\Http\Controllers;

use App\Mail\JobAlertMail;
use App\Models\CareerProfile;
use App\Models\JobListingUserState;
use App\Models\Resume;
use App\Services\Matching\JobMatchService;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * "Job interests": the skills this account wants jobs for. They drive BOTH the feed and the email
 * alerts, so saving them re-scores the recent window straight away.
 */
class JobInterestController extends Controller
{
    public function edit(Request $request, SkillNormalizer $skills)
    {
        $profile = CareerProfile::forUser($request->user()->id);
        $resume = Resume::current();

        // Skills off the uploaded resume, offered as one-click chips (core stack first, broad skills last).
        $fromResume = $resume
            ? collect($skills->names($resume->skills->pluck('name')->all()))
                ->sortBy(fn ($n) => $skills->isGeneric($n) ? 1 : 0)->values()->all()
            : [];

        $matchCount = JobListingUserState::query()
            ->where('user_id', $request->user()->id)->whereNotNull('matched_at')
            ->where('is_dismissed', false)->where('is_applied', false)->count();

        return view('jobs.interests', [
            'profile' => $profile,
            'fromResume' => $fromResume,
            'matchCount' => $matchCount,
            'windowDays' => JobMatchService::WINDOW_DAYS,
        ]);
    }

    public function update(Request $request, SkillNormalizer $normalizer, JobMatchService $matcher)
    {
        $data = $request->validate([
            'required_skills' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'excluded_keywords' => ['nullable', 'string', 'max:2000'],
            'min_required_matches' => ['required', 'integer', 'min:1', 'max:20'],
            'ats_boards' => ['nullable', 'string', 'max:2000'],
            'work_locations' => ['nullable', 'string', 'max:500'],
            'alert_frequency' => ['required', 'in:hourly,daily'],
            'alert_min_tier' => ['required', 'in:weak,good,strong'],
            'notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        $profile = CareerProfile::forUser($request->user()->id);
        $types = array_values(array_intersect(['remote', 'hybrid', 'onsite'], (array) $request->input('work_types', [])));

        // "greenhouse:stripe, lever:spotify" -> vendor:board pairs we know how to read.
        $boards = collect($this->splitCsv($data['ats_boards'] ?? ''))
            ->filter(fn ($b) => preg_match('/^(greenhouse|lever|ashby):[a-z0-9._-]+$/', $b))
            ->unique()->values()->all();

        $profile->fill([
            // Typed skills go through the canonical catalog: "nest js, graphql" is saved as NestJS, GraphQL.
            'required_skills' => $normalizer->names($data['required_skills'] ?? ''),
            'keywords' => $normalizer->names($data['keywords'] ?? ''),
            'excluded_keywords' => $this->splitCsv($data['excluded_keywords'] ?? ''),
            'min_required_matches' => $data['min_required_matches'],
            'hide_region_restricted' => $request->boolean('hide_region_restricted'),
            // Nothing ticked or all three ticked = no preference.
            'work_types' => count($types) === 3 ? [] : $types,
            'work_locations' => $this->splitCsv($data['work_locations'] ?? ''),
            'social_jobs_enabled' => $request->boolean('social_jobs_enabled'),
            'open_to_work' => $request->boolean('open_to_work'),
            'ats_boards' => $boards,
            'digest_enabled' => $request->boolean('digest_enabled'),
            'alert_frequency' => $data['alert_frequency'],
            'alert_min_tier' => $data['alert_min_tier'],
            'notification_email' => $data['notification_email'] ?? null,
        ]);

        // Only a change to WHAT is wanted needs the feed re-scored; alert settings don't.
        $rematch = $profile->isDirty(['required_skills', 'keywords', 'excluded_keywords', 'min_required_matches', 'hide_region_restricted', 'work_types', 'work_locations', 'social_jobs_enabled', 'open_to_work']);
        $profile->save();

        if (! $profile->hasInterests()) {
            $matcher->backfill($profile);

            return redirect()->route('jobs.interests')->with('status', 'Saved. Pick at least one skill to start getting jobs.');
        }

        $message = 'Saved.';
        if ($rematch || $profile->last_matched_id === 0) {
            $n = $matcher->backfill($profile);
            $message = "Saved. {$n} job" . ($n === 1 ? '' : 's') . ' from the last ' . JobMatchService::WINDOW_DAYS . ' days match your skills.';
        }

        return redirect()->route($rematch ? 'jobs.index' : 'jobs.interests')->with('status', $message);
    }

    // POST /jobs/interests/test — paste a job, see exactly how YOUR skills score it.
    public function test(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
        ]);

        $profile = CareerProfile::forUser($request->user()->id);
        $r = $profile->score($data['title'], $data['description'] ?? '');

        $reason = match (true) {
            $r['matches'] => null,
            ! $profile->hasInterests() => 'You have not picked any skills yet.',
            $r['excluded_by'] !== null => 'Dropped by your exclusion rule: "' . $r['excluded_by'] . '".',
            default => 'None of your chosen skills appear in this text (broad skills like Git or REST never qualify a job on their own).',
        };

        return response()->json($r + ['reason' => $reason]);
    }

    // POST /jobs/interests/test-email — proves the mail setup works, using your current best matches.
    // Marks nothing as notified.
    public function testEmail(Request $request)
    {
        $profile = CareerProfile::forUser($request->user()->id);
        $to = $profile->notificationEmail();

        $states = JobListingUserState::query()
            ->where('user_id', $request->user()->id)->whereNotNull('matched_at')
            ->where('is_dismissed', false)->where('is_applied', false)
            ->whereHas('job', fn ($q) => $q->canonical())
            ->with('job')->orderByDesc('score')->limit(5)->get();

        if ($states->isEmpty()) {
            return back()->withErrors(['alerts' => 'No matching jobs yet, so there is nothing to put in a test email. Save your skills and give the next fetch a moment.']);
        }

        try {
            Mail::to($to)->send(new JobAlertMail($states, $states->count(), array_values((array) ($profile->required_skills ?: $profile->keywords))));
        } catch (\Throwable $e) {
            return back()->withErrors(['alerts' => 'The mail server refused it: ' . $e->getMessage()]);
        }

        return back()->with('status', "Test email sent to {$to}.");
    }

    private function splitCsv(string $value): array
    {
        return collect(explode(',', $value))->map(fn ($v) => trim(strtolower($v)))->filter()->values()->all();
    }
}
