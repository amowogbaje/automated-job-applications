<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortfolioProjectController extends Controller
{
    public function index()
    {
        return view('projects.index', ['projects' => PortfolioProject::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('projects.form', ['project' => new PortfolioProject(['is_public' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        PortfolioProject::create($data);

        return redirect()->route('projects.index')->with('status', 'Project added.');
    }

    public function edit(PortfolioProject $project)
    {
        return view('projects.form', compact('project'));
    }

    public function update(Request $request, PortfolioProject $project): RedirectResponse
    {
        $project->update($this->validated($request));

        return redirect()->route('projects.index')->with('status', 'Project updated.');
    }

    public function destroy(PortfolioProject $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
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
