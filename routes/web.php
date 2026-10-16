<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CvVersionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\OutreachEventController;
use App\Http\Controllers\PortfolioProjectController;
use App\Http\Controllers\ProviderSettingsController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;

// Single-user app: there is intentionally no registration route.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Profile
    Route::resource('services', ServiceController::class)->except('show');
    Route::resource('projects', PortfolioProjectController::class)
        ->parameters(['projects' => 'project'])->except('show');
    Route::resource('cvs', CvVersionController::class)
        ->parameters(['cvs' => 'cv'])->except('show');
    Route::get('/cvs/{cv}/download', [CvVersionController::class, 'download'])->name('cvs.download');
    Route::resource('signatures', SignatureController::class)->except('show');

    // Opportunities
    Route::resource('opportunities', OpportunityController::class);
    Route::patch('opportunities/{opportunity}/status', [OpportunityController::class, 'status'])->name('opportunities.status');
    Route::put('opportunities/{opportunity}/assessment', [OpportunityController::class, 'assess'])->name('opportunities.assess');

    Route::post('opportunities/{opportunity}/evidence', [EvidenceController::class, 'store'])->name('evidence.store');
    Route::delete('evidence/{evidence}', [EvidenceController::class, 'destroy'])->name('evidence.destroy');

    Route::post('opportunities/{opportunity}/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::post('opportunities/{opportunity}/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::delete('follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');

    Route::post('opportunities/{opportunity}/events', [OutreachEventController::class, 'store'])->name('events.store');

    // Research agent
    Route::post('opportunities/{opportunity}/research', [ResearchController::class, 'start'])->name('research.start');
    Route::get('agent-runs/{run}', [ResearchController::class, 'show'])->name('agent-runs.show');

    // Provider settings (AI + search failover)
    Route::get('/settings/providers', [ProviderSettingsController::class, 'index'])->name('providers.index');
    Route::put('/settings/providers', [ProviderSettingsController::class, 'update'])->name('providers.update');
    Route::post('/settings/providers/{kind}/{name}/test', [ProviderSettingsController::class, 'test'])
        ->whereIn('kind', ['ai', 'search'])->name('providers.test');
    Route::post('/settings/providers/{kind}/{name}/reset', [ProviderSettingsController::class, 'reset'])
        ->whereIn('kind', ['ai', 'search'])->name('providers.reset');
});
