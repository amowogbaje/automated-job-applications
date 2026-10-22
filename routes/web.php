<?php

use App\Http\Controllers\Agent\ContactController as AgentContactController;
use App\Http\Controllers\Agent\CvVersionController as AgentCvVersionController;
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\Agent\DiscoveryController as AgentDiscoveryController;
use App\Http\Controllers\Agent\EvidenceController as AgentEvidenceController;
use App\Http\Controllers\Agent\FollowUpController as AgentFollowUpController;
use App\Http\Controllers\Agent\OpportunityController as AgentOpportunityController;
use App\Http\Controllers\Agent\OutreachController as AgentOutreachController;
use App\Http\Controllers\Agent\OutreachEventController as AgentOutreachEventController;
use App\Http\Controllers\Agent\PortfolioProjectController as AgentPortfolioProjectController;
use App\Http\Controllers\Agent\ProviderSettingsController as AgentProviderSettingsController;
use App\Http\Controllers\Agent\OpportunityResumeController as AgentOpportunityResumeController;
use App\Http\Controllers\Agent\ResearchController as AgentResearchController;
use App\Http\Controllers\Agent\ServiceController as AgentServiceController;
use App\Http\Controllers\Agent\SignatureController as AgentSignatureController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobDashboardController;
use App\Http\Controllers\JobInterestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSectionController;
use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

// --- Public ---
Route::get('/', HomeController::class)->name('home');

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

// --- Any signed-in account: job feed, skills, resume, profile ---
Route::middleware('auth')->group(function () {
    // Live "how will these skills be saved?" preview.
    Route::get('/skills/preview', [ProfileSectionController::class, 'previewSkills'])->name('skills.preview');

    // The feed: job boards + social posts, only for the skills this account chose.
    Route::get('/jobs', [JobDashboardController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/interests', [JobInterestController::class, 'edit'])->name('jobs.interests');
    Route::put('/jobs/interests', [JobInterestController::class, 'update'])->name('jobs.interests.update');
    Route::post('/jobs/interests/test', [JobInterestController::class, 'test'])->middleware('throttle:60,1')->name('jobs.interests.test');
    Route::post('/jobs/interests/test-email', [JobInterestController::class, 'testEmail'])->middleware('throttle:5,1')->name('jobs.interests.testEmail');
    Route::post('/jobs/{job}/dismiss', [JobDashboardController::class, 'dismiss'])->whereNumber('job')->name('jobs.dismiss');
    Route::post('/jobs/{job}/applied', [JobDashboardController::class, 'markApplied'])->whereNumber('job')->name('jobs.applied');
    Route::get('/jobs/{job}/resume', [JobDashboardController::class, 'resume'])->whereNumber('job')->name('jobs.resume');
    Route::get('/jobs/{job}/cover-letter', [JobDashboardController::class, 'coverLetter'])->whereNumber('job')->name('jobs.coverLetter');

    // Resume: upload / claim / download, then edit it on the profile page.
    Route::get('/resume/upload', [ResumeController::class, 'showUploadForm'])->name('resume.upload');
    Route::post('/resume/upload', [ResumeController::class, 'upload']);
    Route::post('/resume/claim', [ResumeController::class, 'claim'])->name('resume.claim');
    Route::get('/resume/download', [ResumeController::class, 'download'])->name('resume.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/open-to-work', [ProfileController::class, 'toggleOpenToWork'])->name('profile.openToWork');

    // LinkedIn-style inline editing (all scoped to your active resume)
    Route::patch('/profile/intro', [ProfileSectionController::class, 'intro'])->name('profile.intro');
    Route::get('/profile/skills/preview', [ProfileSectionController::class, 'previewSkills'])->name('profile.skills.preview');
    Route::post('/profile/skills', [ProfileSectionController::class, 'addSkills'])->name('profile.skills.add');
    Route::delete('/profile/skills/{skill}', [ProfileSectionController::class, 'removeSkill'])->name('profile.skills.remove');
    Route::post('/profile/{section}', [ProfileSectionController::class, 'store'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->name('profile.section.store');
    Route::patch('/profile/{section}/{id}', [ProfileSectionController::class, 'update'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->whereNumber('id')->name('profile.section.update');
    Route::delete('/profile/{section}/{id}', [ProfileSectionController::class, 'destroy'])->whereIn('section', ['experience', 'education', 'project', 'certification'])->whereNumber('id')->name('profile.section.destroy');
});

// --- Opportunity Agent (personal: owner account only) ---
Route::middleware(['auth', 'agent.owner'])->prefix('agent')->name('agent.')->group(function () {
    Route::get('/', AgentDashboardController::class)->name('dashboard');

    Route::post('services/suggest', [AgentServiceController::class, 'suggest'])->middleware('throttle:6,1')->name('services.suggest');
    Route::post('services/suggest/store', [AgentServiceController::class, 'storeSuggested'])->name('services.suggest.store');
    Route::resource('services', AgentServiceController::class)->except('show');
    Route::resource('projects', AgentPortfolioProjectController::class)
        ->parameters(['projects' => 'project'])->except('show');
    Route::resource('cvs', AgentCvVersionController::class)
        ->parameters(['cvs' => 'cv'])->except('show');
    Route::get('/cvs/{cv}/download', [AgentCvVersionController::class, 'download'])->name('cvs.download');
    Route::resource('signatures', AgentSignatureController::class)->except('show');

    // Discovery Engine: find candidate companies (saved as "discovered"; nothing is contacted)
    Route::get('discovery', [AgentDiscoveryController::class, 'index'])->name('discovery.index');
    Route::post('discovery', [AgentDiscoveryController::class, 'store'])->middleware('throttle:20,1')->name('discovery.store');
    Route::get('discovery/{run}', [AgentDiscoveryController::class, 'show'])->whereNumber('run')->name('discovery.show');
    Route::get('discovery/{run}/trace', [AgentDiscoveryController::class, 'trace'])->whereNumber('run')->name('discovery.trace');
    Route::get('discovery/{run}/status', [AgentDiscoveryController::class, 'status'])->whereNumber('run')->name('discovery.status');

    Route::resource('opportunities', AgentOpportunityController::class);
    Route::post('opportunities/{opportunity}/resume', [AgentOpportunityResumeController::class, 'generate'])->middleware('throttle:10,1')->name('opportunities.resume');
    Route::get('opportunities/{opportunity}/resume', [AgentOpportunityResumeController::class, 'download'])->name('opportunities.resume.download');
    Route::patch('opportunities/{opportunity}/status', [AgentOpportunityController::class, 'status'])->name('opportunities.status');
    Route::put('opportunities/{opportunity}/assessment', [AgentOpportunityController::class, 'assess'])->name('opportunities.assess');

    Route::post('opportunities/{opportunity}/evidence', [AgentEvidenceController::class, 'store'])->name('evidence.store');
    Route::delete('evidence/{evidence}', [AgentEvidenceController::class, 'destroy'])->name('evidence.destroy');

    Route::post('opportunities/{opportunity}/contacts', [AgentContactController::class, 'store'])->name('contacts.store');
    Route::delete('contacts/{contact}', [AgentContactController::class, 'destroy'])->name('contacts.destroy');

    Route::post('opportunities/{opportunity}/follow-ups', [AgentFollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('follow-ups/{followUp}/complete', [AgentFollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::delete('follow-ups/{followUp}', [AgentFollowUpController::class, 'destroy'])->name('follow-ups.destroy');

    Route::post('opportunities/{opportunity}/events', [AgentOutreachEventController::class, 'store'])->name('events.store');

    // Outreach Studio (drafts only; sending is manual)
    Route::get('opportunities/{opportunity}/outreach', [AgentOutreachController::class, 'show'])->name('outreach.show');
    Route::post('opportunities/{opportunity}/outreach/generate', [AgentOutreachController::class, 'generate'])->middleware('throttle:20,1')->name('outreach.generate');
    Route::patch('outreach-drafts/{draft}', [AgentOutreachController::class, 'update'])->middleware('throttle:240,1')->name('outreach.update');
    Route::post('outreach-drafts/{draft}/reset', [AgentOutreachController::class, 'reset'])->name('outreach.reset');
    Route::post('outreach-drafts/{draft}/select', [AgentOutreachController::class, 'select'])->name('outreach.select');
    Route::post('outreach-drafts/{draft}/copied', [AgentOutreachController::class, 'copied'])->name('outreach.copied');
    Route::post('outreach-drafts/{draft}/discard', [AgentOutreachController::class, 'discard'])->name('outreach.discard');
    Route::post('outreach-drafts/{draft}/sent', [AgentOutreachController::class, 'sent'])->name('outreach.sent');

    Route::post('opportunities/{opportunity}/research', [AgentResearchController::class, 'start'])->name('research.start');
    Route::get('agent-runs/{run}', [AgentResearchController::class, 'show'])->name('agent-runs.show');

    // AI + search provider failover settings
    Route::get('/settings/providers', [AgentProviderSettingsController::class, 'index'])->name('providers.index');
    Route::put('/settings/providers', [AgentProviderSettingsController::class, 'update'])->name('providers.update');
    Route::post('/settings/providers/refresh', [AgentProviderSettingsController::class, 'refresh'])->name('providers.refresh');
    Route::post('/settings/providers/{kind}/{name}/test', [AgentProviderSettingsController::class, 'test'])
        ->whereIn('kind', ['ai', 'search'])->name('providers.test');
    Route::post('/settings/providers/{kind}/{name}/toggle', [AgentProviderSettingsController::class, 'toggle'])
        ->whereIn('kind', ['ai', 'search'])->name('providers.toggle');
    Route::post('/settings/providers/{kind}/{name}/reset', [AgentProviderSettingsController::class, 'reset'])
        ->whereIn('kind', ['ai', 'search'])->name('providers.reset');
});
