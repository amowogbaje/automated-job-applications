<?php

namespace App\Http\Controllers;

use App\Models\CareerProfile;
use App\Models\Resume;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /profile — LinkedIn-style editable resume/profile. (Which jobs you get is on /jobs/interests.)
    public function edit(Request $request)
    {
        $resume = Resume::current();
        $profile = CareerProfile::forUser($request->user()->id);

        return view('profile.edit', [
            'profile' => $profile,
            'resume' => $resume,
            'strength' => $this->strength($resume),
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

    // PATCH /profile/open-to-work
    public function toggleOpenToWork(Request $request)
    {
        $profile = CareerProfile::forUser($request->user()->id);
        $profile->update(['open_to_work' => ! $profile->open_to_work]);

        return back();
    }
}
