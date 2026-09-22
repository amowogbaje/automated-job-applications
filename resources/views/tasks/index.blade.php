@extends('layouts.app')

@section('title', 'Tasks')

@section('content')

    <div class="mb-8">
        <h1 class="font-serif text-2xl font-semibold text-ink">Tasks</h1>
        <p class="text-sm text-ink/60 mt-1">Side to-dos, grouped by project — separate from the job feed itself.</p>
    </div>

    {{-- Project filter + quick "add project" --}}
    <div class="flex flex-wrap items-end gap-4 mb-6">
        <form method="GET" action="{{ route('tasks.index') }}" class="flex items-end gap-2">
            <div>
                <label for="project_id" class="block text-xs font-medium text-ink/50 mb-1">Project</label>
                <select
                    name="project_id"
                    id="project_id"
                    onchange="this.form.submit()"
                    class="rounded-md border-line  text-sm focus:border-forest-dark focus:ring-forest-dark"
                >
                    <option value="">All tasks</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="text-sm px-3 py-2 border rounded-md">Filter</button></noscript>
        </form>

        <details class="text-sm">
            <summary class="cursor-pointer text-forest hover:text-forest-dark select-none">+ New project</summary>
            <form method="POST" action="{{ route('projects.store') }}" class="mt-2 flex gap-2">
                @csrf
                <input
                    type="text"
                    name="name"
                    placeholder="Project name"
                    required
                    class="rounded-md border-line  text-sm focus:border-forest-dark focus:ring-forest-dark"
                >
                <button type="submit" class="text-sm px-3 py-2 bg-forest text-white rounded-md hover:bg-forest-dark">
                    Create
                </button>
            </form>
        </details>
    </div>

    {{-- New task form --}}
    <form method="POST" action="{{ route('tasks.store') }}" class="bg-white border border-line rounded-lg p-4 mb-8 ">
        @csrf
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="name" class="block text-xs font-medium text-ink/50 mb-1">Task name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    required
                    placeholder="e.g. Write the release notes"
                    class="w-full rounded-md border-line  text-sm focus:border-forest-dark focus:ring-forest-dark"
                >
            </div>
            <div>
                <label for="new_task_project_id" class="block text-xs font-medium text-ink/50 mb-1">Project</label>
                <select
                    name="project_id"
                    id="new_task_project_id"
                    class="rounded-md border-line  text-sm focus:border-forest-dark focus:ring-forest-dark"
                >
                    <option value="">No project</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            {{-- Preserve the current filter after redirecting back. --}}
            <input type="hidden" name="redirect_project_id" value="{{ $selectedProjectId }}">
            <button type="submit" class="px-4 py-2 bg-forest text-white text-sm font-medium rounded-md hover:bg-forest-dark">
                Add task
            </button>
        </div>
    </form>

    {{-- Task list --}}
    @if ($tasks->isEmpty())
        <p class="text-sm text-ink/50 italic">
            No tasks {{ $selectedProjectId ? 'in this project' : '' }} yet. Add one above to get started.
        </p>
    @else
        <p class="text-xs text-ink/40 mb-2">Drag the handle (⠿) to reorder. #1 is the top priority.</p>

        <ul id="task-list" class="space-y-2">
            @foreach ($tasks as $task)
                <li
                    data-id="{{ $task->id }}"
                    class="task-row bg-white border border-line rounded-lg  px-3 py-3 flex items-center gap-3"
                >
                    <span class="drag-handle cursor-grab text-line hover:text-ink/50 select-none text-lg leading-none" title="Drag to reorder">
                        ⠿
                    </span>

                    <span class="priority-badge shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-forest-light text-forest-dark text-xs font-semibold">
                        {{ $task->priority }}
                    </span>

                    <div class="flex-1 min-w-0">
                        <div class="view-mode">
                            <p class="text-sm font-medium text-ink truncate">{{ $task->name }}</p>
                            <p class="text-xs text-ink/40">
                                @if ($task->project)
                                    <span class="inline-block bg-paper rounded px-1.5 py-0.5">{{ $task->project->name }}</span>
                                @else
                                    <span class="inline-block text-line">No project</span>
                                @endif
                                &middot; updated {{ $task->updated_at->diffForHumans() }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('tasks.update', $task) }}" class="edit-mode hidden gap-2 mt-1">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="redirect_project_id" value="{{ $selectedProjectId }}">
                            <input
                                type="text"
                                name="name"
                                value="{{ $task->name }}"
                                required
                                class="w-full rounded-md border-line  text-sm focus:border-forest-dark focus:ring-forest-dark"
                            >
                            <select name="project_id" class="mt-2 rounded-md border-line  text-sm focus:border-forest-dark focus:ring-forest-dark">
                                <option value="">No project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected($task->project_id === $project->id)>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 flex gap-2">
                                <button type="submit" class="text-xs px-3 py-1.5 bg-forest text-white rounded-md hover:bg-forest-dark">
                                    Save
                                </button>
                                <button type="button" class="cancel-edit text-xs px-3 py-1.5 border border-line rounded-md hover:bg-paper">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="shrink-0 flex items-center gap-1">
                        <button type="button" class="edit-toggle text-xs px-2 py-1 text-ink/50 hover:text-forest" title="Edit task">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="redirect_project_id" value="{{ $selectedProjectId }}">
                            <button type="submit" class="text-xs px-2 py-1 text-ink/50 hover:text-rust" title="Delete task">
                                Delete
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

@endsection

@push('scripts')
    <script>
        // Toggle inline edit forms.
        document.querySelectorAll('.edit-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('.task-row');
                row.querySelector('.view-mode').classList.toggle('hidden');
                row.querySelector('.edit-mode').classList.toggle('hidden');
                row.querySelector('.edit-mode').classList.toggle('flex');
            });
        });

        document.querySelectorAll('.cancel-edit').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('.task-row');
                row.querySelector('.view-mode').classList.remove('hidden');
                row.querySelector('.edit-mode').classList.add('hidden');
                row.querySelector('.edit-mode').classList.remove('flex');
            });
        });

        // Drag-and-drop reordering.
        const taskList = document.getElementById('task-list');

        if (taskList) {
            Sortable.create(taskList, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function () {
                    const taskIds = Array.from(taskList.querySelectorAll('.task-row')).map(
                        (row) => row.dataset.id
                    );

                    // Update the visible priority badges immediately for snappy feedback.
                    taskList.querySelectorAll('.task-row').forEach((row, index) => {
                        row.querySelector('.priority-badge').textContent = index + 1;
                    });

                    fetch('{{ route('tasks.reorder') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ task_ids: taskIds }),
                    }).catch(() => {
                        alert('Could not save the new order. Please refresh and try again.');
                    });
                },
            });
        }
    </script>
@endpush
