<?php

use App\Http\Controllers\ApplicationDraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Employer\EmployerApplicantController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use App\Http\Controllers\Employer\EmployerJobController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobApplyController;
use App\Http\Controllers\JobDashboardController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\MyApplicationsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSectionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicJobController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\SocialJobController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Webhooks\ApplyServiceWebhookController;
use Illuminate\Support\Facades\Route;

// --- Inbound provider webhooks (no session, no CSRF — signature-verified) ---
Route::post('/webhooks/apply-service', ApplyServiceWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.applyService');

// --- Public ---
Route::get('/', HomeController::class)->name('home');
// A job posted on this site: readable and shareable without an account.
Route::get('/job/{job}', [PublicJobController::class, 'show'])->name('jobs.public');

// --- Native auth (email/password only — no social providers) ---
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

// --- Any signed-in account ---
Route::middleware('auth')->group(function () {
    // Live "how will these skills be saved?" preview, used by the profile AND the post-a-job form.
    Route::get('/skills/preview', [ProfileSectionController::class, 'previewSkills'])->name('skills.preview');

    // The task board is one reactive page; everything below returns JSON.
    Route::controller(TaskController::class)->group(function () {
        Route::get('/tasks', 'index')->name('tasks.index');
        Route::post('/tasks', 'store')->name('tasks.store')->middleware('throttle:120,1');
        Route::post('/tasks/reorder', 'reorder')->name('tasks.reorder')->middleware('throttle:120,1');
        Route::delete('/tasks/completed', 'clearCompleted')->name('tasks.clearCompleted');
        Route::match(['put', 'patch'], '/tasks/{task}', 'update')->whereNumber('task')->name('tasks.update')->middleware('throttle:240,1');
        Route::delete('/tasks/{task}', 'destroy')->whereNumber('task')->name('tasks.destroy');
        Route::post('/tasks/{id}/restore', 'restore')->whereNumber('id')->name('tasks.restore');
    });
    Route::controller(ProjectController::class)->group(function () {
        Route::post('/projects', 'store')->name('projects.store');
        Route::patch('/projects/{project}', 'update')->whereNumber('project')->name('projects.update');
        Route::delete('/projects/{project}', 'destroy')->whereNumber('project')->name('projects.destroy');
    });

    Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
    Route::get('/leads/discover', [LeadsController::class, 'showDiscoverForm'])->name('leads.discover');
    Route::post('/leads/discover', [LeadsController::class, 'discover']);
    Route::post('/leads', [LeadsController::class, 'store'])->name('leads.store');
    Route::patch('/leads/{lead}', [LeadsController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/reveal', [LeadsController::class, 'reveal'])->name('leads.reveal');
    Route::delete('/leads/{lead}', [LeadsController::class, 'destroy'])->name('leads.destroy');
});

// --- Job seekers ---
Route::middleware(['auth', 'role:seeker'])->group(function () {
    Route::get('/jobs', [JobDashboardController::class, 'index'])->name('jobs.index');
    Route::post('/jobs/{job}/dismiss', [JobDashboardController::class, 'dismiss'])->name('jobs.dismiss');
    Route::post('/jobs/{job}/applied', [JobDashboardController::class, 'markApplied'])->name('jobs.applied');
    Route::get('/jobs/{job}/resume', [JobDashboardController::class, 'resume'])->name('jobs.resume');
    Route::get('/jobs/{job}/cover-letter', [JobDashboardController::class, 'coverLetter'])->name('jobs.coverLetter');

    // Apply to a job that an employer posted on this site
    Route::get('/job/{job}/apply', [JobApplyController::class, 'create'])->name('jobs.apply.create');
    Route::post('/job/{job}/apply', [JobApplyController::class, 'store'])->name('jobs.apply.store');
    Route::post('/job/{job}/apply/draft-letter', [JobApplyController::class, 'draftLetter'])->name('jobs.apply.draftLetter');
    Route::get('/applications', [MyApplicationsController::class, 'index'])->name('applications.index');

    Route::get('/social', [SocialJobController::class, 'index'])->name('social.index');
    Route::post('/social/{job}/send', [SocialJobController::class, 'send'])->name('social.send');

    Route::get('/drafts', [ApplicationDraftController::class, 'index'])->name('drafts.index');
    Route::patch('/drafts/{draft}', [ApplicationDraftController::class, 'update'])->name('drafts.update');
    Route::post('/drafts/{draft}/sent', [ApplicationDraftController::class, 'markSent'])->name('drafts.sent');
    Route::post('/drafts/{draft}/discard', [ApplicationDraftController::class, 'discard'])->name('drafts.discard');
    Route::get('/drafts/{draft}/resume', [ResumeController::class, 'downloadForDraft'])->name('drafts.resume');

    Route::get('/resume/upload', [ResumeController::class, 'showUploadForm'])->name('resume.upload');
    Route::post('/resume/upload', [ResumeController::class, 'upload']);
    Route::post('/resume/claim', [ResumeController::class, 'claim'])->name('resume.claim');
    Route::get('/resume/download', [ResumeController::class, 'download'])->name('resume.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/social', [ProfileController::class, 'updateSocial'])->name('profile.social');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::patch('/profile/open-to-work', [ProfileController::class, 'toggleOpenToWork'])->name('profile.openToWork');
    Route::post('/profile/test-match', [ProfileController::class, 'testMatch'])->name('profile.testMatch');
    Route::patch('/profile/auto-apply', [ProfileController::class, 'updateAutoApply'])->name('profile.autoApply');
    Route::post('/profile/from-resume', [ProfileController::class, 'populateFromResume'])->name('profile.fromResume');

    // LinkedIn-style inline editing (all scoped to your active resume)
    Route::patch('/profile/intro', [ProfileSectionController::class, 'intro'])->name('profile.intro');
    Route::get('/profile/skills/preview', [ProfileSectionController::class, 'previewSkills'])->name('profile.skills.preview');
    Route::post('/profile/skills', [ProfileSectionController::class, 'addSkills'])->name('profile.skills.add');
    Route::delete('/profile/skills/{skill}', [ProfileSectionController::class, 'removeSkill'])->name('profile.skills.remove');
    Route::post('/profile/{section}', [ProfileSectionController::class, 'store'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->name('profile.section.store');
    Route::patch('/profile/{section}/{id}', [ProfileSectionController::class, 'update'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->whereNumber('id')->name('profile.section.update');
    Route::delete('/profile/{section}/{id}', [ProfileSectionController::class, 'destroy'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->whereNumber('id')->name('profile.section.destroy');
});

// --- Employers ---
Route::middleware(['auth', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('/', [EmployerDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/company', [EmployerDashboardController::class, 'company'])->name('company');
    Route::patch('/company', [EmployerDashboardController::class, 'updateCompany'])->name('company.update');

    Route::get('/jobs', [EmployerJobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/create', [EmployerJobController::class, 'create'])->name('jobs.create');
    Route::post('/jobs', [EmployerJobController::class, 'store'])->middleware('throttle:30,1')->name('jobs.store');
    Route::get('/jobs/{job}/edit', [EmployerJobController::class, 'edit'])->name('jobs.edit');
    Route::patch('/jobs/{job}', [EmployerJobController::class, 'update'])->middleware('throttle:30,1')->name('jobs.update');
    Route::post('/jobs/{job}/close', [EmployerJobController::class, 'close'])->name('jobs.close');
    Route::post('/jobs/{job}/reopen', [EmployerJobController::class, 'reopen'])->name('jobs.reopen');
    Route::post('/jobs/{job}/duplicate', [EmployerJobController::class, 'duplicate'])->name('jobs.duplicate');
    Route::delete('/jobs/{job}', [EmployerJobController::class, 'destroy'])->name('jobs.destroy');

    Route::get('/applicants', [EmployerApplicantController::class, 'index'])->name('applicants');
    Route::patch('/applications/{application}', [EmployerApplicantController::class, 'update'])->name('applications.update');
    Route::get('/applications/{application}/resume', [EmployerApplicantController::class, 'resume'])->name('applications.resume');
});
