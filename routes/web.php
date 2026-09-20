<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApplicationDraftController;
use App\Http\Controllers\JobDashboardController;

// Route::redirect('/', 'tasks');

Route::controller(TaskController::class)->group(function () {
    Route::get('/tasks', 'index')->name('tasks.index');
    Route::post('/tasks', 'store')->name('tasks.store');
    Route::put('/tasks/{task}', 'update')->name('tasks.update');
    Route::delete('/tasks/{task}', 'destroy')->name('tasks.destroy');
    Route::post('/tasks/reorder', 'reorder')->name('tasks.reorder');
});

Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');


Route::get('/', [JobDashboardController::class, 'index'])->name('jobs.index');
Route::post('/jobs/{job}/dismiss', [JobDashboardController::class, 'dismiss'])->name('jobs.dismiss');
Route::post('/jobs/{job}/applied', [JobDashboardController::class, 'markApplied'])->name('jobs.applied');

Route::get('/drafts', [ApplicationDraftController::class, 'index'])->name('drafts.index');
Route::patch('/drafts/{draft}', [ApplicationDraftController::class, 'update'])->name('drafts.update');
Route::post('/drafts/{draft}/sent', [ApplicationDraftController::class, 'markSent'])->name('drafts.sent');
Route::post('/drafts/{draft}/discard', [ApplicationDraftController::class, 'discard'])->name('drafts.discard');
