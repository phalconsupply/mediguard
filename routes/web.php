<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckRuleController;
use App\Http\Controllers\ColumnPresetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\ErrorCatalogController;
use App\Http\Controllers\ExplanationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportBatchController;
use App\Http\Controllers\IssueCaseController;
use App\Http\Controllers\PrecheckController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Tiếp nhận đợt và nạp tệp
    Route::get('/batches', [ImportBatchController::class, 'index'])->name('batches.index');
    Route::get('/batches/create', [ImportBatchController::class, 'create'])->name('batches.create');
    Route::post('/batches', [ImportBatchController::class, 'store'])->name('batches.store');
    Route::get('/batches/{batch}', [ImportBatchController::class, 'show'])->name('batches.show');
    Route::post('/batches/{batch}/files', [ImportBatchController::class, 'upload'])->name('batches.upload');
    Route::get('/files/{file}/preview', [ImportBatchController::class, 'preview'])->name('files.preview');
    Route::post('/files/{file}/reingest', [ImportBatchController::class, 'reingest'])->name('files.reingest');

    // Giải trình
    Route::get('/cases', [IssueCaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{case}', [IssueCaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/reassign', [IssueCaseController::class, 'reassign'])->name('cases.reassign');
    Route::post('/cases/{case}/explanations', [ExplanationController::class, 'store'])->name('explanations.store');
    Route::post('/explanations/{explanation}/approve', [ExplanationController::class, 'approve'])->name('explanations.approve');
    Route::post('/explanations/{explanation}/return', [ExplanationController::class, 'returnToAuthor'])->name('explanations.return');

    // Xuất trả
    Route::get('/batches/{batch}/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('/files/{file}/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{artifact}/download', [ExportController::class, 'download'])->name('exports.download');

    // Thư viện lỗi
    Route::get('/catalog', [ErrorCatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{catalog}', [ErrorCatalogController::class, 'show'])->name('catalog.show');
    Route::put('/catalog/{catalog}', [ErrorCatalogController::class, 'update'])->name('catalog.update');
    Route::put('/catalog-variants/{variant}', [ErrorCatalogController::class, 'reviewVariant'])->name('catalog.variants.review');

    // Quy tắc tự kiểm tra
    Route::get('/rules', [CheckRuleController::class, 'index'])->name('rules.index');
    Route::get('/rules/create', [CheckRuleController::class, 'create'])->name('rules.create');
    Route::post('/rules', [CheckRuleController::class, 'store'])->name('rules.store');
    Route::get('/rules/{rule}', [CheckRuleController::class, 'show'])->name('rules.show');
    Route::post('/rules/{rule}/versions', [CheckRuleController::class, 'storeVersion'])->name('rules.versions.store');
    Route::post('/rule-versions/{version}/transition', [CheckRuleController::class, 'transition'])->name('rules.versions.transition');

    // Tự kiểm tra trước khi gửi
    Route::get('/precheck', [PrecheckController::class, 'index'])->name('precheck.index');
    Route::get('/batches/{batch}/precheck/create', [PrecheckController::class, 'create'])->name('precheck.create');
    Route::post('/batches/{batch}/precheck', [PrecheckController::class, 'store'])->name('precheck.store');
    Route::get('/precheck/{run}', [PrecheckController::class, 'show'])->name('precheck.show');
    Route::post('/findings/{finding}/handle', [PrecheckController::class, 'handle'])->name('precheck.findings.handle');

    // Cấu hình cột hiển thị
    Route::get('/presets', [ColumnPresetController::class, 'index'])->name('presets.index');
    Route::post('/presets', [ColumnPresetController::class, 'store'])->name('presets.store');
    Route::delete('/presets/{preset}', [ColumnPresetController::class, 'destroy'])->name('presets.destroy');

    // Danh mục quản trị
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::post('/directory/departments', [DirectoryController::class, 'storeDepartment'])->name('directory.departments.store');
    Route::post('/directory/staff', [DirectoryController::class, 'storeStaff'])->name('directory.staff.store');
    Route::post('/directory/service-owners', [DirectoryController::class, 'storeServiceOwner'])->name('directory.services.store');
    Route::post('/directory/assignment-rules', [DirectoryController::class, 'storeAssignmentRule'])->name('directory.rules.store');
});
