<?php

use App\Http\Controllers\ApplicationDraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobDashboardController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\ProfileController;
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
    Route::get('/jobs/{job}/resume', [JobDashboardController::class, 'resume'])->name('jobs.resume');
    Route::get('/jobs/{job}/cover-letter', [JobDashboardController::class, 'coverLetter'])->name('jobs.coverLetter');

    Route::get('/drafts', [ApplicationDraftController::class, 'index'])->name('drafts.index');
    Route::patch('/drafts/{draft}', [ApplicationDraftController::class, 'update'])->name('drafts.update');
    Route::post('/drafts/{draft}/sent', [ApplicationDraftController::class, 'markSent'])->name('drafts.sent');
    Route::post('/drafts/{draft}/discard', [ApplicationDraftController::class, 'discard'])->name('drafts.discard');

    Route::get('/resume/upload', [ResumeController::class, 'showUploadForm'])->name('resume.upload');
    Route::post('/resume/upload', [ResumeController::class, 'upload']);
    Route::post('/resume/claim', [ResumeController::class, 'claim'])->name('resume.claim');
    Route::get('/resume/download', [ResumeController::class, 'download'])->name('resume.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/from-resume', [ProfileController::class, 'populateFromResume'])->name('profile.fromResume');
    Route::get('/drafts/{draft}/resume', [ResumeController::class, 'downloadForDraft'])->name('drafts.resume');

    Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
    Route::get('/leads/discover', [LeadsController::class, 'showDiscoverForm'])->name('leads.discover');
    Route::post('/leads/discover', [LeadsController::class, 'discover']);
    Route::post('/leads', [LeadsController::class, 'store'])->name('leads.store');
    Route::patch('/leads/{lead}', [LeadsController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/reveal', [LeadsController::class, 'reveal'])->name('leads.reveal');
    Route::delete('/leads/{lead}', [LeadsController::class, 'destroy'])->name('leads.destroy');
});
