<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The task board is a single reactive page: this controller renders it once with the user's data
 * embedded, and every action after that is a small JSON call (no page reloads). All tasks are
 * scoped to the signed-in user and share ONE priority order per user (1 = top).
 */
class TaskController extends Controller
{
    // GET /tasks
    public function index(Request $request): View
    {
        $uid = $request->user()->id;

        return view('tasks.index', [
            'tasks' => Task::where('user_id', $uid)->orderBy('priority')->orderBy('id')->get()->map->toBoardArray()->values(),
            'projects' => Project::where('user_id', $uid)->orderBy('name')->get()->map->toBoardArray()->values(),
            'selectedProjectId' => $request->integer('project_id') ?: null,
        ]);
    }

    // POST /tasks
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $uid = $request->user()->id;
        $data = $request->validated();

        $task = Task::create([
            'user_id' => $uid,
            'name' => $data['name'],
            'project_id' => $data['project_id'] ?? null,
            'priority' => $this->nextPriority($uid),
        ]);

        return response()->json(['task' => $task->toBoardArray()], 201);
    }

    // PATCH /tasks/{task}: rename, move to another project, and/or (un)complete
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->own($request, $task);
        $uid = $request->user()->id;
        $data = $request->validated();

        // Moving to another project sends it to the bottom of the list, like adding it fresh.
        if (array_key_exists('project_id', $data) && $data['project_id'] !== $task->project_id) {
            $data['priority'] = $this->nextPriority($uid);
        }

        if (array_key_exists('completed', $data)) {
            $data['completed_at'] = $data['completed'] ? ($task->completed_at ?? now()) : null;
            unset($data['completed']);
        }

        $task->update($data);

        return response()->json(['task' => $task->fresh()->toBoardArray()]);
    }

    // DELETE /tasks/{task}: soft delete, so the page can offer "Undo"
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->own($request, $task);
        $task->delete();
        $this->resequence($request->user()->id);

        return response()->json(['id' => $task->id]);
    }

    // POST /tasks/{id}/restore
    public function restore(Request $request, int $id): JsonResponse
    {
        $uid = $request->user()->id;
        $task = Task::withTrashed()->where('user_id', $uid)->findOrFail($id);

        $task->restore();
        $task->update(['priority' => $this->nextPriority($uid)]); // the page then re-sends the order to put it back

        return response()->json(['task' => $task->fresh()->toBoardArray()]);
    }

    // DELETE /tasks/completed?project_id=
    public function clearCompleted(Request $request): JsonResponse
    {
        $uid = $request->user()->id;

        $ids = Task::where('user_id', $uid)->whereNotNull('completed_at')
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->query('scope') === 'none', fn ($q) => $q->whereNull('project_id'))
            ->pluck('id');

        Task::whereIn('id', $ids)->delete();
        $this->resequence($uid);

        return response()->json(['ids' => $ids->values()]);
    }

    // POST /tasks/reorder: body is the new order, first id becomes priority 1
    public function reorder(ReorderTasksRequest $request): JsonResponse
    {
        $uid = $request->user()->id;
        $ids = $request->validated('task_ids');

        DB::transaction(function () use ($ids, $uid) {
            foreach ($ids as $index => $id) {
                Task::where('user_id', $uid)->whereKey($id)->update(['priority' => $index + 1]);
            }
        });

        return response()->json(['status' => 'ok']);
    }

    // ------------------------------------------------------------------

    private function own(Request $request, Task $task): void
    {
        abort_unless($task->user_id === $request->user()->id, 404);
    }

    private function nextPriority(int $userId): int
    {
        return (int) Task::where('user_id', $userId)->max('priority') + 1;
    }

    /** Renumber the user's tasks 1..n with no gaps, keeping their current order. */
    private function resequence(int $userId): void
    {
        $n = 0;
        foreach (Task::where('user_id', $userId)->orderBy('priority')->orderBy('id')->get(['id', 'priority']) as $task) {
            $n++;
            if ($task->priority !== $n) {
                Task::whereKey($task->id)->update(['priority' => $n]);
            }
        }
    }
}
