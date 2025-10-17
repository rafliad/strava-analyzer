<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\StravaController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;

use App\Http\Controllers\PersonalRecordsController;

Route::get('/personal-records', [PersonalRecordsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('personal-records');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
});

Route::middleware('auth')->prefix('analysis')->name('analysis.')->group(function () {
    Route::get('/', [AnalysisController::class, 'index'])->name('index');
    Route::post('/perform', [AnalysisController::class, 'performAnalysis'])->name('perform');
    Route::post('/activity/{activity}', [AnalysisController::class, 'performSingleActivityAnalysis'])->name('performSingle');
});

Route::get('/activities/{activity}', [ActivityController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('activities.show');

require __DIR__ . '/auth.php';
