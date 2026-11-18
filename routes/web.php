<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/brand/logo', [BrandController::class, 'logo'])->name('brand.logo');
Route::post('/locale', [AuthController::class, 'locale'])->name('locale');

// First run: no company pack installed yet.
Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup', [SetupController::class, 'install'])->name('setup.install');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/auth/microsoft', [AuthController::class, 'redirectToMicrosoft'])->name('auth.microsoft');
    Route::get('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Employee journey
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('/policies', [PortalController::class, 'policies'])->name('policies.index');
    Route::post('/policies', [PortalController::class, 'signPolicies'])->name('policies.sign');
    Route::get('/policies/{policy}/document', [PortalController::class, 'policyDocument'])->name('policies.document');
    Route::get('/modules/{module}', [PortalController::class, 'module'])->name('modules.show');
    Route::post('/modules/{module}/complete', [PortalController::class, 'completeModule'])->name('modules.complete');
    Route::get('/quiz', [PortalController::class, 'quiz'])->name('quiz.show');
    Route::post('/quiz', [PortalController::class, 'submitQuiz'])->name('quiz.submit');
    Route::get('/quiz/attempts/{attempt}', [PortalController::class, 'quizResult'])->name('quiz.result');
    Route::get('/certificate', [PortalController::class, 'certificate'])->name('certificate');

    // Supervised kiosk mode
    Route::prefix('kiosk')->name('kiosk.')->group(function () {
        Route::get('/unlock', [KioskController::class, 'unlockForm'])->name('unlock');
        Route::post('/unlock', [KioskController::class, 'unlock'])->name('unlock.store');
        Route::get('/', [KioskController::class, 'index'])->name('index');
        Route::post('/verify', [KioskController::class, 'verify'])->name('verify');
        Route::post('/questions', [KioskController::class, 'questions'])->name('questions');
        Route::post('/submit', [KioskController::class, 'submit'])->name('submit');
        Route::post('/exit', [KioskController::class, 'exit'])->name('exit');
    });

    // Administration. hr_auditor can read everything and maintain the roster; the rest is admin only.
    Route::prefix('admin')->name('admin.')->middleware('role:admin,hr_auditor')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/employees/{user}', [Admin\EmployeeController::class, 'show'])->name('employees.show')->whereNumber('user');
        Route::get('/employees/create', [Admin\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [Admin\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{user}/edit', [Admin\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{user}', [Admin\EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/employees/{user}/remind', [Admin\EmployeeController::class, 'remind'])->name('employees.remind');
        Route::get('/employees/{user}/certificate', [Admin\EmployeeController::class, 'certificate'])->name('employees.certificate');

        Route::get('/import', [Admin\ImportController::class, 'form'])->name('import');
        Route::post('/import/preview', [Admin\ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/apply', [Admin\ImportController::class, 'apply'])->name('import.apply');

        Route::get('/sessions', [Admin\SessionController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/{session}', [Admin\SessionController::class, 'show'])->name('sessions.show');

        Route::get('/exports', [Admin\ExportController::class, 'index'])->name('exports');
        Route::get('/exports/compliance', [Admin\ExportController::class, 'compliance'])->name('exports.compliance');
        Route::get('/exports/attendance', [Admin\ExportController::class, 'attendance'])->name('exports.attendance');
        Route::get('/exports/audit', [Admin\ExportController::class, 'audit'])->name('exports.audit');

        Route::get('/audit', [Admin\AuditController::class, 'index'])->name('audit');
        Route::get('/content', [Admin\ContentController::class, 'index'])->name('content');

        Route::middleware('role:admin')->group(function () {
            Route::post('/sessions', [Admin\SessionController::class, 'store'])->name('sessions.store');
            Route::put('/sessions/{session}/attendance', [Admin\SessionController::class, 'attendance'])->name('sessions.attendance');
            Route::delete('/sessions/{session}', [Admin\SessionController::class, 'destroy'])->name('sessions.destroy');

            Route::get('/content/{type}/create', [Admin\ContentController::class, 'create'])->name('content.create');
            Route::post('/content/{type}', [Admin\ContentController::class, 'store'])->name('content.store');
            Route::get('/content/{type}/{id}/edit', [Admin\ContentController::class, 'edit'])->name('content.edit');
            Route::put('/content/{type}/{id}', [Admin\ContentController::class, 'update'])->name('content.update');

            Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings');
            Route::put('/settings/kiosk-pin', [Admin\SettingsController::class, 'kioskPin'])->name('settings.kiosk-pin');
            Route::put('/settings/quiz', [Admin\SettingsController::class, 'quiz'])->name('settings.quiz');
            Route::post('/settings/campaigns', [Admin\SettingsController::class, 'campaign'])->name('settings.campaign');
            Route::post('/settings/logo', [Admin\SettingsController::class, 'logo'])->name('settings.logo');
            Route::delete('/settings/logo', [Admin\SettingsController::class, 'logoRemove'])->name('settings.logo.remove');
            Route::post('/settings/pack', [Admin\SettingsController::class, 'packUpload'])->name('settings.pack');
            Route::get('/settings/pack/export', [Admin\SettingsController::class, 'packExport'])->name('settings.pack.export');
        });
    });
});
