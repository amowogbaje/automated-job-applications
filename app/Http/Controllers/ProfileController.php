<?php

namespace App\Http\Controllers;

use App\Models\CareerProfile;
use App\Models\Resume;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /profile
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'profile' => CareerProfile::forUser($request->user()->id),
            'resume' => Resume::current(),
        ]);
    }

    // PATCH /profile — manual edit, same comma-separated format the old
    // .env vars used, just typed into a form instead.
    public function update(Request $request)
    {
        $data = $request->validate([
            'keywords' => ['nullable', 'string', 'max:2000'],
            'required_skills' => ['nullable', 'string', 'max:2000'],
            'excluded_keywords' => ['nullable', 'string', 'max:2000'],
            'min_required_matches' => ['required', 'integer', 'min:1', 'max:20'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'auto_send_enabled' => ['nullable', 'boolean'],
            'digest_enabled' => ['nullable', 'boolean'],
        ]);

        CareerProfile::forUser($request->user()->id)->update([
            // Typed skills go through the canonical catalog: "nest js, graphql,
            // microservices" is saved as NestJS, GraphQL, Microservices.
            'keywords' => app(SkillNormalizer::class)->names($data['keywords'] ?? ''),
            'required_skills' => app(SkillNormalizer::class)->names($data['required_skills'] ?? ''),
            'excluded_keywords' => $this->splitCsv($data['excluded_keywords'] ?? ''),
            'min_required_matches' => $data['min_required_matches'],
            'notification_email' => $data['notification_email'] ?? null,
            'auto_send_enabled' => $request->boolean('auto_send_enabled'),
            'digest_enabled' => $request->boolean('digest_enabled'),
        ]);

        return back()->with('status', 'Career profile saved — your jobs feed and email automation use this from now on.');
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

        return back()->with('status', 'Auto-apply settings saved.');
    }

    // POST /profile/from-resume — pulls every skill off your active resume
    // into both "keywords" and "required skills". Doesn't touch excluded
    // keywords or the match threshold — those aren't derivable from a resume.
    public function populateFromResume(Request $request)
    {
        $resume = Resume::current();

        if (! $resume || $resume->skills->isEmpty()) {
            return back()->withErrors(['profile' => 'No active resume with skills to pull from — import one first at /resume/upload.']);
        }

        $profile = CareerProfile::forUser($request->user()->id);
        $profile->fillFromResume($resume);
        $profile->save();

        return back()->with('status', 'Pulled ' . count($profile->keywords) . ' skills from your resume into keywords and required skills below — review and save.');
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
