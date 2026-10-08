<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CvController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\JobMatchController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

// Auth (stricter limit against credential stuffing)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
});

// Public job board
Route::middleware('throttle:api')->group(function () {
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');

    // In-app notifications (any signed-in user; always scoped to that user)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Candidate area
    Route::middleware('role:candidate')->group(function () {
        Route::apiResource('applications', ApplicationController::class);
        Route::get('activity', ActivityController::class)->name('activity.index');

        // CV analyzer (AI endpoints are rate limited per user to control cost)
        Route::apiResource('cvs', CvController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('cvs/{cv}/analyze', [CvController::class, 'analyze'])->middleware('throttle:ai')->name('cvs.analyze');
        Route::post('jobs/{job}/match', JobMatchController::class)->middleware('throttle:ai')->name('jobs.match');

        // AI interview coach
        Route::apiResource('interviews', InterviewController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::middleware('throttle:ai-interview')->group(function () {
            Route::post('interviews/{interview}/start', [InterviewController::class, 'start'])->name('interviews.start');
            Route::post('interviews/{interview}/complete', [InterviewController::class, 'complete'])->name('interviews.complete');
            // scopeBindings: the question must belong to this interview, otherwise 404.
            Route::post('interviews/{interview}/questions/{question}/answer', [InterviewController::class, 'answer'])
                ->scopeBindings()->name('interviews.answer');
        });
    });

    // Admin area
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('stats', Admin\StatsController::class)->name('stats');
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::apiResource('companies', Admin\CompanyController::class);
        Route::apiResource('jobs', Admin\JobController::class);
        Route::apiResource('skills', Admin\SkillController::class);
    });
});
