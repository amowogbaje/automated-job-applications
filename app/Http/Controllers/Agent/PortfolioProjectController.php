<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\PortfolioProject;
use App\Services\Agent\Profile\ProfileSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortfolioProjectController extends Controller
{
    public function index(ProfileSync $sync)
    {
        $sync->syncProjects();

        return view('agent.projects.index', ['projects' => PortfolioProject::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('agent.projects.form', ['project' => new PortfolioProject(['is_public' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        PortfolioProject::create($data);

        return redirect()->route('agent.projects.index')->with('status', 'Project added.');
    }

    public function edit(PortfolioProject $project)
    {
        return view('agent.projects.form', compact('project'));
    }

    public function update(Request $request, PortfolioProject $project): RedirectResponse
    {
        $data = $this->validated($request);
        if ($project->isFromProfile()) {
            // Name, URL, summary and technologies belong to the Profile; only agent-side fields change here.
            $data = array_intersect_key($data, array_flip(['industries', 'proof_points', 'is_public']));
        }
        $project->update($data);

        return redirect()->route('agent.projects.index')->with('status', 'Project updated.');
    }

    public function destroy(PortfolioProject $project): RedirectResponse
    {
        if ($project->isFromProfile()) {
            return back()->with('status', 'This project comes from your Profile. Remove it there and it disappears here too.');
        }
        $project->delete();

        return redirect()->route('agent.projects.index')->with('status', 'Project deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'technologies' => ['nullable', 'array'],
            'technologies.*' => ['string', 'max:100'],
            'industries' => ['nullable', 'array'],
            'industries.*' => ['string', 'max:100'],
            'proof_points' => ['nullable', 'string', 'max:3000'],
        ]);
        $data['technologies'] = array_values($data['technologies'] ?? []);
        $data['industries'] = array_values($data['industries'] ?? []);
        $data['is_public'] = $request->boolean('is_public');

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $i = 2;
        while (PortfolioProject::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
