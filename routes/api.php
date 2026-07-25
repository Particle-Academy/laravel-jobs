<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ParticleAcademy\LaravelJobs\Http\Controllers\Api\ApplicationController;
use ParticleAcademy\LaravelJobs\Http\Controllers\Api\EmployerApplicationController;
use ParticleAcademy\LaravelJobs\Http\Controllers\Api\EmployerPostingController;
use ParticleAcademy\LaravelJobs\Http\Controllers\Api\JobBoardController;

/*
|--------------------------------------------------------------------------
| Public board
|--------------------------------------------------------------------------
| Visible postings only. Safe to expose unauthenticated.
*/
Route::get('postings', [JobBoardController::class, 'index'])->name('jobs.postings.index');
Route::get('postings/{slug}', [JobBoardController::class, 'show'])->name('jobs.postings.show');

/*
|--------------------------------------------------------------------------
| Candidate
|--------------------------------------------------------------------------
| Identified by CandidateResolver; ownership is by user_id.
*/
Route::post('postings/{slug}/applications', [ApplicationController::class, 'store'])->name('jobs.applications.store');
Route::get('my-applications', [ApplicationController::class, 'index'])->name('jobs.applications.index');
Route::post('applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('jobs.applications.withdraw');

/*
|--------------------------------------------------------------------------
| Employer
|--------------------------------------------------------------------------
| Every route below is gated by the host's AuthorizesEmployers binding, which
| denies by default. Mounting these without binding one exposes nothing.
*/
Route::prefix('employers/{employer}')->group(function (): void {
    Route::get('postings', [EmployerPostingController::class, 'index'])->name('jobs.employer.postings.index');
    Route::post('postings', [EmployerPostingController::class, 'store'])->name('jobs.employer.postings.store');
    Route::get('postings/{posting}', [EmployerPostingController::class, 'show'])->name('jobs.employer.postings.show');
    Route::patch('postings/{posting}', [EmployerPostingController::class, 'update'])->name('jobs.employer.postings.update');
    Route::delete('postings/{posting}', [EmployerPostingController::class, 'destroy'])->name('jobs.employer.postings.destroy');

    Route::post('postings/{posting}/publish', [EmployerPostingController::class, 'publish'])->name('jobs.employer.postings.publish');
    Route::post('postings/{posting}/unpublish', [EmployerPostingController::class, 'unpublish'])->name('jobs.employer.postings.unpublish');
    Route::post('postings/{posting}/close', [EmployerPostingController::class, 'close'])->name('jobs.employer.postings.close');

    Route::get('applications', [EmployerApplicationController::class, 'index'])->name('jobs.employer.applications.index');
    Route::patch('applications/{application}', [EmployerApplicationController::class, 'updateStatus'])->name('jobs.employer.applications.update');
});
