<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\CvVersion;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Services\Agent\Profile\ProfileSync;
use App\Services\Agent\Profile\ServiceSuggester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index()
    {
        return view('agent.services.index', [
            'services' => Service::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    /** Propose services from the Profile (nothing is saved until you tick and confirm). */
    public function suggest(ProfileSync $profile, ServiceSuggester $suggester)
    {
        $resume = $profile->ownerResume();
        if (! $resume) {
            return redirect()->route('agent.services.index')->withErrors(['suggest' => 'Import your resume first; services are suggested from your Profile.']);
        }
        $profile->syncProjects();

        return view('agent.services.suggest', ['result' => $suggester->suggest($resume)]);
    }

    public function storeSuggested(Request $request, ProfileSync $profile): RedirectResponse
    {
        $request->validate(['pick' => ['required', 'array', 'min:1'], 'pick.*' => ['integer'], 'items' => ['required', 'array']]);
        $resume = $profile->ownerResume();
        $made = 0;
        foreach ((array) $request->input('pick') as $i) {
            $item = json_decode((string) ($request->input('items')[$i] ?? ''), true);
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }
            // Re-validate against the resume: the form data is only a request, not trusted.
            $clean = ServiceSuggester::clean([$item], $resume?->skills->pluck('name')->all() ?? [], $resume?->projects->pluck('name')->all() ?? [])[0] ?? null;
            if (! $clean || Service::where('slug', Str::slug($clean['name']))->exists()) {
                continue;
            }
            $service = Service::create([
                'name' => $clean['name'], 'slug' => $this->uniqueSlug($clean['name']), 'description' => $clean['description'],
                'skills_required' => $clean['skills_required'], 'search_keywords' => $clean['search_keywords'],
                'target_businesses' => $clean['target_businesses'], 'opportunity_signals' => $clean['opportunity_signals'],
                'preferred_engagement' => $clean['preferred_engagement'], 'is_employment' => $clean['is_employment'],
                'is_active' => true, 'sort_order' => 0, 'geographic_preferences' => [],
            ]);
            $this->linkProjects($service, $clean['skills_required']);
            $made++;
        }

        return redirect()->route('agent.services.index')->with('status', $made ? "{$made} service(s) added from your Profile." : 'Nothing new was added.');
    }

    /** Link portfolio projects whose technologies overlap the service's skills (relevance 1-5 by overlap). */
    private function linkProjects(Service $service, array $skills): void
    {
        $want = array_map('mb_strtolower', $skills);
        $sync = [];
        foreach (PortfolioProject::all() as $p) {
            $hits = count(array_intersect($want, array_map('mb_strtolower', (array) $p->technologies)));
            if ($hits > 0) {
                $sync[$p->id] = ['relevance' => max(1, min(5, $hits + 1))];
            }
        }
        $service->portfolioProjects()->sync($sync);
    }

    public function create()
    {
        return view('agent.services.form', $this->formData(new Service(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::create($this->validated($request) + [
            'slug' => $this->uniqueSlug($request->input('name')),
        ]);
        $this->syncRelations($service, $request);

        return redirect()->route('agent.services.index')->with('status', 'Service created.');
    }

    public function edit(Service $service)
    {
        return view('agent.services.form', $this->formData($service));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request));
        $this->syncRelations($service, $request);

        return redirect()->route('agent.services.index')->with('status', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('agent.services.index')->with('status', 'Service deleted.');
    }

    private function formData(Service $service): array
    {
        return [
            'service' => $service,
            'projects' => PortfolioProject::orderBy('name')->get(),
            'cvs' => CvVersion::whereNull('opportunity_id')->orderBy('name')->get(),
            'linkedProjects' => $service->exists
                ? $service->portfolioProjects->pluck('pivot.relevance', 'id')->all() : [],
            'linkedCvs' => $service->exists ? $service->cvVersions->pluck('id')->all() : [],
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'skills_required' => ['nullable', 'array'],
            'skills_required.*' => ['string', 'max:100'],
            'target_businesses' => ['nullable', 'array'],
            'target_businesses.*' => ['string', 'max:150'],
            'opportunity_signals' => ['nullable', 'array'],
            'opportunity_signals.*' => ['string', 'max:200'],
            'search_keywords' => ['nullable', 'array'],
            'search_keywords.*' => ['string', 'max:150'],
            'geographic_preferences' => ['nullable', 'array'],
            'geographic_preferences.*' => ['string', 'max:100'],
            'preferred_engagement' => ['required', 'in:any,employment,contract,project,consulting'],
            'minimum_budget' => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['required', 'string', 'size:3'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        foreach (['skills_required', 'target_businesses', 'opportunity_signals', 'search_keywords', 'geographic_preferences'] as $key) {
            $data[$key] = array_values($data[$key] ?? []);
        }
        $data['budget_currency'] = strtoupper($data['budget_currency']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_employment'] = $request->boolean('is_employment');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function syncRelations(Service $service, Request $request): void
    {
        $relevance = $request->input('relevance', []);
        $projects = [];
        foreach ((array) $request->input('project_ids', []) as $id) {
            $projects[(int) $id] = ['relevance' => max(1, min(5, (int) ($relevance[$id] ?? 3)))];
        }
        $service->portfolioProjects()->sync($projects);
        $service->cvVersions()->sync(array_map('intval', (array) $request->input('cv_ids', [])));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $i = 2;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
