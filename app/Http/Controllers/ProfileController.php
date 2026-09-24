<?php

namespace App\Http\Controllers;

use App\Models\CareerProfile;
use App\Models\Resume;
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
            'keywords' => $this->splitCsv($data['keywords'] ?? ''),
            'required_skills' => $this->splitCsv($data['required_skills'] ?? ''),
            'excluded_keywords' => $this->splitCsv($data['excluded_keywords'] ?? ''),
            'min_required_matches' => $data['min_required_matches'],
            'notification_email' => $data['notification_email'] ?? null,
            'auto_send_enabled' => $request->boolean('auto_send_enabled'),
            'digest_enabled' => $request->boolean('digest_enabled'),
        ]);

        return back()->with('status', 'Career profile saved — your jobs feed and email automation use this from now on.');
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
