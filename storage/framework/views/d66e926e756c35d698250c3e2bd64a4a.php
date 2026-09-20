<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Application Drafts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        .draft { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .draft h2 { font-size: 1.05rem; margin: 0 0 .25rem; }
        .draft .meta { color: #666; font-size: .85rem; margin-bottom: .5rem; }
        .draft textarea { width: 100%; min-height: 160px; font-family: inherit; padding: .5rem; box-sizing: border-box; }
        .draft .tailored { background: #fff9e6; border: 1px solid #f0e0a0; padding: .5rem .75rem; border-radius: 6px; font-size: .85rem; margin-bottom: .5rem; }
        .draft .actions { margin-top: .5rem; display: flex; gap: .5rem; }
        .status { padding: .5rem; background: #e6ffe6; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <h1>Application Drafts — review before sending</h1>

    <?php if(session('status')): ?>
        <div class="status"><?php echo e(session('status')); ?></div>
    <?php endif; ?>

    <?php $__empty_1 = true; $__currentLoopData = $drafts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="draft">
            <h2><a href="<?php echo e($draft->job->url); ?>" target="_blank" rel="noopener"><?php echo e($draft->job->title); ?></a></h2>
            <div class="meta"><?php echo e($draft->job->company); ?> &middot; <?php echo e($draft->job->source); ?></div>

            <?php if($draft->tailored_summary): ?>
                <div class="tailored"><strong>Emphasis for this role:</strong> <?php echo e($draft->tailored_summary); ?></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('drafts.update', $draft)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>
                <textarea name="cover_letter"><?php echo e($draft->cover_letter); ?></textarea>
                <div class="actions">
                    <button type="submit">Save edits</button>
                </div>
            </form>

            <div class="actions">
                <form method="POST" action="<?php echo e(route('drafts.sent', $draft)); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit">I've sent this — mark applied</button>
                </form>
                <form method="POST" action="<?php echo e(route('drafts.discard', $draft)); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit">Discard</button>
                </form>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p>No drafts yet. Run <code>php artisan applications:generate</code> to create some from your top-matching jobs.</p>
    <?php endif; ?>

    <?php echo e($drafts->links()); ?>

</body>
</html>
<?php /**PATH C:\xampp\htdocs\task-manager\resources\views/drafts/index.blade.php ENDPATH**/ ?>