<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\StravaController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalysisController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::get('/activities', [ActivityController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('activities.index');

Route::middleware('auth')->prefix('strava')->name('strava.')->group(function () {
    Route::get('/connect', [StravaController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [StravaController::class, 'callback']);
    Route::post('/disconnect', [StravaController::class, 'disconnect'])->name('disconnect');
    Route::post('/sync', [StravaController::class, 'sync'])->name('sync');
    Route::get('/dashboard-stats', [StravaController::class, 'dashboardStats'])->name('dashboardStats');
    Route::get('/athlete', [StravaController::class, 'athlete']);
    Route::get('/activity/{id}', [StravaController::class, 'singleActivity']);
});

Route::get('/analysis', [AnalysisController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('analysis.index');

Route::post('/analysis/perform', [AnalysisController::class, 'performAnalysis'])
    ->middleware(['auth', 'verified'])
    ->name('analysis.perform');

require __DIR__ . '/auth.php';
