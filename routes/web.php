<?php

use App\Http\Controllers\ApplicationDraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobDashboardController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSectionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Webhooks\ApplyServiceWebhookController;
use Illuminate\Support\Facades\Route;

// --- Inbound provider webhooks (no session, no CSRF — signature-verified) ---
Route::post('/webhooks/apply-service', ApplyServiceWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.applyService');

// --- Native auth (email/password only — no social providers) ---
Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
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

    Route::get('/jobs', [JobDashboardController::class, 'index'])->name('jobs.index');
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
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::patch('/profile/open-to-work', [ProfileController::class, 'toggleOpenToWork'])->name('profile.openToWork');
    Route::post('/profile/test-match', [ProfileController::class, 'testMatch'])->name('profile.testMatch');

    // LinkedIn-style inline editing (all scoped to your active resume)
    Route::patch('/profile/intro', [ProfileSectionController::class, 'intro'])->name('profile.intro');
    Route::get('/profile/skills/preview', [ProfileSectionController::class, 'previewSkills'])->name('profile.skills.preview');
    Route::post('/profile/skills', [ProfileSectionController::class, 'addSkills'])->name('profile.skills.add');
    Route::delete('/profile/skills/{skill}', [ProfileSectionController::class, 'removeSkill'])->name('profile.skills.remove');
    Route::post('/profile/{section}', [ProfileSectionController::class, 'store'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->name('profile.section.store');
    Route::patch('/profile/{section}/{id}', [ProfileSectionController::class, 'update'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->whereNumber('id')->name('profile.section.update');
    Route::delete('/profile/{section}/{id}', [ProfileSectionController::class, 'destroy'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->whereNumber('id')->name('profile.section.destroy');

    Route::patch('/profile/auto-apply', [ProfileController::class, 'updateAutoApply'])->name('profile.autoApply');
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
