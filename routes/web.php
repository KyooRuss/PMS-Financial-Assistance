<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Office\AssessmentController;
use App\Http\Controllers\Office\CompletenessCheckController;
use App\Http\Controllers\Office\DecisionController;
use App\Http\Controllers\Office\FinalEvaluationController;
use App\Http\Controllers\Office\ProcessingTimeController;
use App\Http\Controllers\Office\ProgramOfWorkController;
use App\Http\Controllers\Office\ProjectReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:lwd'])->group(function () {
    Route::get('/projects/create/{type}', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    // Page 8 – post-evaluation documents
    Route::get('/projects/{project}/compliance', [ComplianceController::class, 'edit'])->name('projects.compliance');
    Route::put('/projects/{project}/compliance', [ComplianceController::class, 'update'])->name('projects.compliance.update');
});

// System Admin: PMS overall. View only on projects; manages accounts and water districts.
Route::middleware(['auth', 'role:system_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\OverviewController::class)->name('overview');
    Route::get('/projects', [Admin\ProjectController::class, 'index'])->name('projects.index');

    Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);
    Route::patch('/users/{user}/active', [Admin\UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('/users/{user}/password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::resource('water-districts', Admin\WaterDistrictController::class)->except(['show', 'destroy']);
});

// Processing Time report, for every LWUA account
Route::get('/reports/processing-time', ProcessingTimeController::class)
    ->middleware(['auth', 'role:wsudd,es,lwrd,cpic,administrator,bfmd,lpmd,legal,system_admin'])
    ->name('reports.processing-time');

// LWUA offices (the admin side). The System Admin may open the review page; every action route below
// carries its own office role, so the System Admin can only view.
Route::middleware(['auth', 'role:wsudd,es,lwrd,cpic,administrator,bfmd,lpmd,legal,system_admin'])
    ->prefix('office')->name('office.')->group(function () {
        Route::get('/projects/{project}', [ProjectReviewController::class, 'show'])->name('projects.show');

        Route::middleware('role:wsudd')->group(function () {
            Route::post('/projects/{project}/endorse', [CompletenessCheckController::class, 'endorse'])->name('projects.endorse');
            Route::post('/projects/{project}/return', [CompletenessCheckController::class, 'return'])->name('projects.return');
        });

        Route::post('/projects/{project}/pow-assessment', [AssessmentController::class, 'pow'])
            ->middleware('role:es')->name('projects.pow-assessment');
        Route::post('/projects/{project}/viability-assessment', [AssessmentController::class, 'viability'])
            ->middleware('role:lwrd')->name('projects.viability-assessment');
        Route::post('/projects/{project}/priority', [AssessmentController::class, 'priority'])
            ->middleware('role:cpic')->name('projects.priority');

        Route::middleware('role:es')->group(function () {
            Route::post('/projects/{project}/pow/prepare', [ProgramOfWorkController::class, 'prepare'])->name('projects.pow.prepare');
            Route::post('/projects/{project}/pow/forward', [ProgramOfWorkController::class, 'forward'])->name('projects.pow.forward');
        });
        Route::middleware('role:lwrd')->group(function () {
            Route::post('/projects/{project}/compliance/request', [FinalEvaluationController::class, 'requestCompliance'])->name('projects.compliance.request');
            Route::post('/projects/{project}/compliance/return', [FinalEvaluationController::class, 'returnCompliance'])->name('projects.compliance.return');
            Route::post('/projects/{project}/compliance/accept', [FinalEvaluationController::class, 'acceptCompliance'])->name('projects.compliance.accept');
            Route::post('/projects/{project}/endorse-to-administrator', [FinalEvaluationController::class, 'endorse'])->name('projects.endorse-to-administrator');
        });
        Route::post('/projects/{project}/caf', [FinalEvaluationController::class, 'issueCaf'])
            ->middleware('role:bfmd')->name('projects.caf');

        Route::middleware('role:administrator')->group(function () {
            Route::post('/projects/{project}/pow/approve', [ProgramOfWorkController::class, 'approve'])->name('projects.pow.approve');
            Route::post('/projects/{project}/pow/return', [ProgramOfWorkController::class, 'returnForRevision'])->name('projects.pow.return');
            Route::post('/projects/{project}/approve', [DecisionController::class, 'approve'])->name('projects.approve');
            Route::post('/projects/{project}/disapprove', [DecisionController::class, 'disapprove'])->name('projects.disapprove');
        });

        Route::middleware('role:lwrd')->group(function () {
            Route::post('/projects/{project}/approval-letter', [DecisionController::class, 'approvalLetter'])->name('projects.approval-letter');
            Route::post('/projects/{project}/disapproval-letter', [DecisionController::class, 'disapprovalLetter'])->name('projects.disapproval-letter');
        });
        Route::post('/projects/{project}/endorse-to-legal', [DecisionController::class, 'endorseToLegal'])
            ->middleware('role:lpmd')->name('projects.endorse-to-legal');
        Route::post('/projects/{project}/contract', [DecisionController::class, 'contract'])
            ->middleware('role:legal')->name('projects.contract');
    });

Route::middleware('auth')->group(function () {
    Route::get('/documents/{document}', [ProjectController::class, 'document'])->name('documents.show');
});

// Only the System Admin manages their own name, email and password. Everyone else's account is managed by the System Admin.
Route::middleware(['auth', 'role:system_admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
