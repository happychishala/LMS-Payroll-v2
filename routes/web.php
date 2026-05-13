<?php

use App\Http\Controllers\BorrowersController;
use App\Http\Controllers\CustomerHistoryDocumentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoanStatementExportController;
use App\Http\Controllers\RepaymentImportCorrectionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::resource('borrower',BorrowersController::class);
    Route::get('/customer-history/document', CustomerHistoryDocumentController::class)
        ->name('customer-history.documents.show');
});


Route::get('/loan-statement/export', [LoanStatementExportController::class, 'export'])
    ->name('loan.statement.export');

Route::post('/filament/repayments/import-corrections', [RepaymentImportCorrectionController::class, 'import'])
    ->name('repayments.import.corrections')
    ->middleware(['auth']); // adjust middleware as needed (Filament uses auth)
