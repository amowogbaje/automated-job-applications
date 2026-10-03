<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // POST /projects
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create(['user_id' => $request->user()->id, 'name' => $request->validated('name')]);

        return response()->json(['project' => $project->toBoardArray()], 201);
    }

    // PATCH /projects/{project}
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->own($request, $project);
        $project->update(['name' => $request->validated('name')]);

        return response()->json(['project' => $project->toBoardArray()]);
    }

    // DELETE /projects/{project}: tasks are kept and become "No project"
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->own($request, $project);

        Task::withTrashed()->where('project_id', $project->id)->update(['project_id' => null]);
        $project->delete();

        return response()->json(['id' => $project->id]);
    }

    private function own(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 404);
    }
}
