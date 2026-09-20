<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e(config('app.name', 'Task Manager')); ?></title>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold text-slate-900"><?php echo e(config('app.name', 'Task Manager')); ?></h1>
            <p class="text-sm text-slate-500 mt-1">Create tasks, drag to reorder, and filter by project.</p>
        </header>

        <?php if(session('status')): ?>
            <div class="mb-6 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                <?php echo e(session('status')); ?>

            </div>
        <?php endif; ?>

        <?php if($errors->any()): ?>
            <div class="mb-6 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php echo $__env->yieldContent('content'); ?>
    </div>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\task-manager\resources\views/layouts/app.blade.php ENDPATH**/ ?>