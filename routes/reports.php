<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Reports\EmployerCsvReportController;

Route::prefix('reports')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::get('employer-csv', [EmployerCsvReportController::class, 'index'])->name('reports.employer_csv.index');
        Route::post('employer-csv/export', [EmployerCsvReportController::class, 'export'])->name('reports.employer_csv.export');
    });
});
