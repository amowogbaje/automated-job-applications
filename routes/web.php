<?php

use App\Http\Controllers\ApplicationDraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobDashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// --- Native auth (email/password only — no social providers) ---
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// --- Everything below requires a login ---
Route::middleware('auth')->group(function () {
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

    Route::get('/resume/upload', [ResumeController::class, 'showUploadForm'])->name('resume.upload');
    Route::post('/resume/upload', [ResumeController::class, 'upload']);
    Route::get('/resume/download', [ResumeController::class, 'download'])->name('resume.download');
    Route::get('/drafts/{draft}/resume', [ResumeController::class, 'downloadForDraft'])->name('drafts.resume');
});
