<?php $__env->startSection('content'); ?>

    
    <div class="flex flex-wrap items-end gap-4 mb-6">
        <form method="GET" action="<?php echo e(route('tasks.index')); ?>" class="flex items-end gap-2">
            <div>
                <label for="project_id" class="block text-xs font-medium text-slate-500 mb-1">Project</label>
                <select
                    name="project_id"
                    id="project_id"
                    onchange="this.form.submit()"
                    class="rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All tasks</option>
                    <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($project->id); ?>" <?php if($selectedProjectId === $project->id): echo 'selected'; endif; ?>>
                            <?php echo e($project->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <noscript><button type="submit" class="text-sm px-3 py-2 border rounded-md">Filter</button></noscript>
        </form>

        <details class="text-sm">
            <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 select-none">+ New project</summary>
            <form method="POST" action="<?php echo e(route('projects.store')); ?>" class="mt-2 flex gap-2">
                <?php echo csrf_field(); ?>
                <input
                    type="text"
                    name="name"
                    placeholder="Project name"
                    required
                    class="rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <button type="submit" class="text-sm px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">
                    Create
                </button>
            </form>
        </details>
    </div>

    
    <form method="POST" action="<?php echo e(route('tasks.store')); ?>" class="bg-white border border-slate-200 rounded-lg p-4 mb-8 shadow-sm">
        <?php echo csrf_field(); ?>
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="name" class="block text-xs font-medium text-slate-500 mb-1">Task name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    required
                    placeholder="e.g. Write the release notes"
                    class="w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>
            <div>
                <label for="new_task_project_id" class="block text-xs font-medium text-slate-500 mb-1">Project</label>
                <select
                    name="project_id"
                    id="new_task_project_id"
                    class="rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">No project</option>
                    <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($project->id); ?>" <?php if($selectedProjectId === $project->id): echo 'selected'; endif; ?>>
                            <?php echo e($project->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            
            <input type="hidden" name="redirect_project_id" value="<?php echo e($selectedProjectId); ?>">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-500">
                Add task
            </button>
        </div>
    </form>

    
    <?php if($tasks->isEmpty()): ?>
        <p class="text-sm text-slate-500 italic">
            No tasks <?php echo e($selectedProjectId ? 'in this project' : ''); ?> yet. Add one above to get started.
        </p>
    <?php else: ?>
        <p class="text-xs text-slate-400 mb-2">Drag the handle (⠿) to reorder. #1 is the top priority.</p>

        <ul id="task-list" class="space-y-2">
            <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li
                    data-id="<?php echo e($task->id); ?>"
                    class="task-row bg-white border border-slate-200 rounded-lg shadow-sm px-3 py-3 flex items-center gap-3"
                >
                    <span class="drag-handle cursor-grab text-slate-300 hover:text-slate-500 select-none text-lg leading-none" title="Drag to reorder">
                        ⠿
                    </span>

                    <span class="priority-badge shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold">
                        <?php echo e($task->priority); ?>

                    </span>

                    <div class="flex-1 min-w-0">
                        <div class="view-mode">
                            <p class="text-sm font-medium text-slate-800 truncate"><?php echo e($task->name); ?></p>
                            <p class="text-xs text-slate-400">
                                <?php if($task->project): ?>
                                    <span class="inline-block bg-slate-100 rounded px-1.5 py-0.5"><?php echo e($task->project->name); ?></span>
                                <?php else: ?>
                                    <span class="inline-block text-slate-300">No project</span>
                                <?php endif; ?>
                                &middot; updated <?php echo e($task->updated_at->diffForHumans()); ?>

                            </p>
                        </div>

                        <form method="POST" action="<?php echo e(route('tasks.update', $task)); ?>" class="edit-mode hidden gap-2 mt-1">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PUT'); ?>
                            <input type="hidden" name="redirect_project_id" value="<?php echo e($selectedProjectId); ?>">
                            <input
                                type="text"
                                name="name"
                                value="<?php echo e($task->name); ?>"
                                required
                                class="w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <select name="project_id" class="mt-2 rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">No project</option>
                                <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($project->id); ?>" <?php if($task->project_id === $project->id): echo 'selected'; endif; ?>>
                                        <?php echo e($project->name); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="mt-2 flex gap-2">
                                <button type="submit" class="text-xs px-3 py-1.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">
                                    Save
                                </button>
                                <button type="button" class="cancel-edit text-xs px-3 py-1.5 border border-slate-300 rounded-md hover:bg-slate-50">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="shrink-0 flex items-center gap-1">
                        <button type="button" class="edit-toggle text-xs px-2 py-1 text-slate-500 hover:text-indigo-600" title="Edit task">
                            Edit
                        </button>
                        <form method="POST" action="<?php echo e(route('tasks.destroy', $task)); ?>" onsubmit="return confirm('Delete this task?');">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <input type="hidden" name="redirect_project_id" value="<?php echo e($selectedProjectId); ?>">
                            <button type="submit" class="text-xs px-2 py-1 text-slate-500 hover:text-red-600" title="Delete task">
                                Delete
                            </button>
                        </form>
                    </div>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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

                    fetch('<?php echo e(route('tasks.reorder')); ?>', {
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\task-manager\resources\views/tasks/index.blade.php ENDPATH**/ ?>