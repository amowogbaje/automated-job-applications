<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return redirect()
            ->route('tasks.index', ['project_id' => $project->id])
            ->with('status', "Project \"{$project->name}\" created.");
    }
}
