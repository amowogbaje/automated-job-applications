<?php

use App\Http\Controllers\AmowogbajeWebsite\BriefController;
use Illuminate\Support\Facades\Route;

// The "api/" prefix is added automatically: POST /api/amowogbajewebsite/send-brief
Route::prefix('amowogbajewebsite')->group(function () {
    Route::post('send-brief', [BriefController::class, 'send'])->middleware('throttle:brief');
});
