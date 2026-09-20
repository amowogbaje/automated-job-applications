<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Job Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        form.filters { display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        form.filters input, form.filters select { padding: .4rem; }
        .job { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1rem; margin-bottom: .75rem; }
        .job h2 { font-size: 1.05rem; margin: 0 0 .25rem; }
        .job .meta { color: #666; font-size: .85rem; margin-bottom: .5rem; }
        .job .actions a, .job .actions button { font-size: .8rem; margin-right: .5rem; }
        .badge { display: inline-block; background: #eef; color: #335; border-radius: 4px; padding: .1rem .4rem; font-size: .75rem; margin-right: .25rem; }
        .pagination { margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Job Feed (<?php echo e($jobs->total()); ?> matches) &middot; <a href="<?php echo e(route('drafts.index')); ?>" style="font-size:.9rem">View drafts</a></h1>

    <form class="filters" method="GET">
        <input type="text" name="q" placeholder="Filter keyword..." value="<?php echo e($keyword); ?>">
        <select name="window">
            <option value="24" <?php if($window == '24'): echo 'selected'; endif; ?>>Last 24 hours</option>
            <option value="72" <?php if($window == '72'): echo 'selected'; endif; ?>>Last 3 days</option>
            <option value="all" <?php if($window == 'all'): echo 'selected'; endif; ?>>All time</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php $__empty_1 = true; $__currentLoopData = $jobs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $job): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="job">
            <h2><a href="<?php echo e($job->url); ?>" target="_blank" rel="noopener"><?php echo e($job->title); ?></a></h2>
            <div class="meta">
                <?php echo e($job->company ?? 'Unknown company'); ?>

                &middot; <?php echo e($job->location ?? 'N/A'); ?>

                &middot; <?php echo e($job->posted_at?->diffForHumans() ?? 'unknown date'); ?>

                &middot; <span class="badge"><?php echo e($job->source); ?></span>
                <?php $__currentLoopData = $job->matched_keywords ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="badge"><?php echo e($kw); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="actions">
                <form method="POST" action="<?php echo e(route('jobs.applied', $job)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit">Mark applied</button>
                </form>
                <form method="POST" action="<?php echo e(route('jobs.dismiss', $job)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit">Dismiss</button>
                </form>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p>No matching jobs in this window yet. Try widening the time range.</p>
    <?php endif; ?>

    <div class="pagination">
        <?php echo e($jobs->links()); ?>

    </div>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\task-manager\resources\views/jobs/index.blade.php ENDPATH**/ ?>