<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\CvVersion;
use App\Models\PortfolioProject;
use App\Models\Service;
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
            'cvs' => CvVersion::orderBy('name')->get(),
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
