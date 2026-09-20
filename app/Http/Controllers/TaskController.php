<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * Display a listing of the tasks, optionally scoped to a single project.
     */
    public function index(Request $request): View
    {
        $projectId = $request->integer('project_id') ?: null;

        $tasks = Task::query()
            ->with('project')
            ->when($request->filled('project_id'), fn ($query) => $query->where('project_id', $projectId))
            ->orderBy('priority')
            ->get();

        $projects = Project::query()->orderBy('name')->get();

        return view('tasks.index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'selectedProjectId' => $projectId,
        ]);
    }

    /**
     * Store a newly created task, placing it last within its project scope.
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $nextPriority = Task::query()
            ->where('project_id', $data['project_id'] ?? null)
            ->max('priority');

        Task::create([
            'name' => $data['name'],
            'project_id' => $data['project_id'] ?? null,
            'priority' => ($nextPriority ?? 0) + 1,
        ]);

        return redirect()
            ->route('tasks.index', $this->currentFilter($request))
            ->with('status', 'Task created.');
    }

    /**
     * Update the given task's name and/or project.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $newProjectId = $data['project_id'] ?? null;

        if ($newProjectId !== $task->project_id) {
            // Moving to a different project (or to/from "no project"): append
            // the task to the end of the new scope's priority order.
            $nextPriority = Task::query()->where('project_id', $newProjectId)->max('priority');
            $data['priority'] = ($nextPriority ?? 0) + 1;
        }

        $task->update($data);

        return redirect()
            ->route('tasks.index', $this->currentFilter($request))
            ->with('status', 'Task updated.');
    }

    /**
     * Remove the given task and resequence the remaining tasks in its scope.
     */
    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $projectId = $task->project_id;

        $task->delete();

        $this->resequence($projectId);

        return redirect()
            ->route('tasks.index', $this->currentFilter($request))
            ->with('status', 'Task deleted.');
    }

    /**
     * Build the query parameters needed to redirect back to the project
     * filter the user was viewing when they submitted the form.
     *
     * @return array<string, int>
     */
    private function currentFilter(Request $request): array
    {
        return $request->filled('redirect_project_id')
            ? ['project_id' => (int) $request->input('redirect_project_id')]
            : [];
    }

    /**
     * Persist a new drag-and-drop order for a set of tasks. The first id in
     * the array becomes priority 1 (top), the second priority 2, and so on.
     */
    public function reorder(ReorderTasksRequest $request): JsonResponse
    {
        $taskIds = $request->validated('task_ids');

        DB::transaction(function () use ($taskIds) {
            foreach ($taskIds as $index => $taskId) {
                Task::whereKey($taskId)->update(['priority' => $index + 1]);
            }
        });

        return response()->json(['status' => 'ok']);
    }

    /**
     * Renumber a project's (or the unassigned) task priorities as 1..n with
     * no gaps, preserving their current relative order.
     */
    private function resequence(?int $projectId): void
    {
        $tasks = Task::query()
            ->where('project_id', $projectId)
            ->orderBy('priority')
            ->get();

        foreach ($tasks as $index => $task) {
            $task->update(['priority' => $index + 1]);
        }
    }
}
