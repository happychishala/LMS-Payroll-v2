<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\GenericExport;
use Livewire\Attributes\On;
use App\Services\LoanInterestAccrualService;

class RecoveryModule extends Page
{
    protected static ?string $navigationGroup = 'Repayments';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static string $view = 'filament.pages.recovery-module';
    protected static ?string $title = 'Loan Recovery Performance';

    public $selectedMonth;
    public $selectedYear;
    public $selectedLoanType;
    public $selectedStatus = 'All';

    public $filterMonth;
    public $filterYear;
    public $filterLoanType;
    public $filterStatus;

    public $loanTypes = [];

    public $perPage = 10;
    public $page = 1;

    public $recoveryData = [];
    public $paginatedData = [];

    // initialize summary counters to avoid "Undefined array key" when incrementing
    public $summaryStats = [
        'performing' => 0,
        'missed' => 0,
        'settled' => 0,
        'amount_missed' => 0.0,
        'amount_collected' => 0.0,
    ];

    public function mount()
    {
        $this->loanTypes = DB::table('loan_types')->pluck('loan_name')->unique()->toArray();
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->applyFilter();
    }

    #[On('applyFilter')]
    public function applyFilter()
    {
        // ensure filters are updated
        $this->filterMonth = $this->selectedMonth;
        $this->filterYear  = $this->selectedYear;
        $this->filterLoanType = $this->selectedLoanType;
        $this->filterStatus = $this->selectedStatus;
        $this->page = 1;

        Log::info('RecoveryModule.applyFilter called', [
            'selectedMonth' => $this->selectedMonth,
            'selectedYear' => $this->selectedYear,
            'filterStatus' => $this->filterStatus,
            'filterLoanType' => $this->filterLoanType,
        ]);

        $this->loadData();
    }

    public function goToPage($pageNumber)
    {
        $this->page = $pageNumber;
        $this->loadData();
    }

    protected function loadData()
    {
        // Reset summary statistics before loading new data
        $this->summaryStats = [
            'performing' => 0,
            'missed' => 0,
            'settled' => 0,
            'amount_missed' => 0.0,
            'amount_collected' => 0.0,
        ];
        $this->recoveryData = [];

        $targetMonth = (int) $this->filterMonth;
        $targetYear  = (int) $this->filterYear;
        $selectedMonthEnd = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->endOfMonth();
        $targetMonth = (int) $this->filterMonth;
        $targetYear  = (int) $this->filterYear;
        $selectedMonthEnd = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->endOfMonth();

        // current date used to skip future due dates
        $today = \Carbon\Carbon::today();

        // local copies of filters to avoid undefined variable errors
        $filterStatus   = $this->filterStatus ?? null;
        $filterLoanType = $this->filterLoanType ?? null;
        $filterMonth    = $this->filterMonth ?? $this->selectedMonth;
        $filterYear     = $this->filterYear  ?? $this->selectedYear;
        
        // build base query (no where on dates yet)
        $loansQuery = DB::table('loans')
            ->join('loan_types', 'loans.loan_type_id', '=', 'loan_types.id')
            ->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->select(
                'loans.loan_id',
                'loans.loan_release_date',
                'loans.total_monthly_repayment',
                'loans.loan_duration',
                'loans.loan_status',
                'loan_types.loan_name',
                'borrowers.employer',
                'borrowers.customer_id as borrower_id',
                'borrowers.first_name',
                'borrowers.other_names',
                'borrowers.last_name'
            );

        // apply optional filters (loan type / status)
        if (!empty($this->filterLoanType)) {
            $loansQuery->where('loan_types.loan_name', $this->filterLoanType);
        }
        // do not filter by loan_release_date here; we'll compute firstPaymentDate per loan

        Log::info('RecoveryModule.loadData SQL', [
            'sql' => $loansQuery->toSql(),
            'bindings' => $loansQuery->getBindings(),
            'selectedMonthEnd' => $selectedMonthEnd->toDateString(),
        ]);

        $loans = $loansQuery->get();

        // quick early-debug: if no loans returned, log and stop
        if ($loans->isEmpty()) {
            Log::warning('RecoveryModule.loadData: no loans found from base query');
        }

        // proceed with your existing loop but add a debug for first loan to inspect firstPaymentDate
        $interestAccruals = app(LoanInterestAccrualService::class);

        foreach ($loans as $idx => $loan) {
            $issueDate = \Carbon\Carbon::parse($loan->loan_release_date);

            // mark settled/closed loans (handle different status text variants)
            $status = strtoupper(trim($loan->loan_status ?? ''));
            $isSettled = in_array($status, ['SETTLED', 'CLOSED', 'PAID OFF', 'PAIDOFF', 'PAID'], true);
            
            $firstPaymentDate = $interestAccruals->firstPaymentDate($loan)?->copy() ?? $issueDate->copy()->endOfMonth();

            if ($idx === 0) {
                Log::info('RecoveryModule.firstLoanDebug', [
                    'loan_id' => $loan->loan_id,
                    'issueDate' => $issueDate->toDateString(),
                    'firstPaymentDate' => $firstPaymentDate->toDateString(),
                    'selectedMonthEnd' => $selectedMonthEnd->toDateString(),
                ]);
            }

            // Skip if first payment is beyond selected month
            if ($firstPaymentDate->greaterThan($selectedMonthEnd)) {
                continue;
            }

            // Number of repayment months to consider (from first payment up to selected month)
            $monthsToProcess = $firstPaymentDate->diffInMonths($selectedMonthEnd) + 1;
            $monthsToProcess = min($monthsToProcess, $loan->loan_duration);

            for ($i = 0; $i < $monthsToProcess; $i++) {
                $dueDate = $firstPaymentDate->copy()->addMonths($i)->endOfMonth();

                // Skip if due date is in the future
                if ($dueDate->greaterThan($today)) {
                    continue;
                }

                // Only process if due date falls in the selected month
                if ($dueDate->month !== $targetMonth || $dueDate->year !== $targetYear) {
                    continue;
                }

                $monthStart = $dueDate->copy()->startOfMonth();
                $monthEnd = $dueDate->copy()->endOfMonth();

                $paid = DB::table('repayments')
                    ->where('loan_id', $loan->loan_id)
                    ->whereBetween('receipt_date', [$monthStart, $monthEnd])
                    ->sum('receipt_amount');

                // Determine status
                if ($isSettled) {
                    $status = 'Settled';
                } elseif ($paid >= $loan->total_monthly_repayment) {
                    $status = 'Performing';
                } elseif ($paid > 0) {
                    $status = 'Underpaid';
                } else {
                    $status = 'Missed';
                }

                // Apply filters
                if ($filterStatus === 'Performing' && $status !== 'Performing') continue;
                if ($filterStatus === 'Missed' && $status !== 'Missed') continue;
                if ($filterStatus === 'Underpaid' && $status !== 'Underpaid') continue;
                if ($filterStatus === 'Settled' && $status !== 'Settled') continue;

                $borrowerName = trim(collect([
                    $loan->first_name ?: $loan->other_names,
                    $loan->last_name,
                ])->filter()->implode(' '));

                $this->recoveryData[] = [
                    'loan_id' => $loan->loan_id,
                    'borrower_id' => $loan->borrower_id,
                    'borrower_name' => $borrowerName ?: 'N/A',
                    'loan_release_date' => $loan->loan_release_date,
                    'month' => $dueDate->format('F Y'),
                    'due_date' => $dueDate->toDateString(),
                    'expected' => $loan->total_monthly_repayment,
                    'paid' => $paid,
                    'status' => $status,
                    'loan_name' => $loan->loan_name,
                ];

                // Update statistics
                if ($status === 'Performing') {
                    $this->summaryStats['performing']++;
                    $this->summaryStats['amount_collected'] += (float) $paid;
                } elseif ($status === 'Underpaid') {
                    $this->summaryStats['amount_collected'] += (float) $paid;
                    $this->summaryStats['amount_missed'] += max(0, (float) $loan->total_monthly_repayment - (float) $paid);
                } elseif ($status === 'Missed') {
                    $this->summaryStats['missed']++;
                    $this->summaryStats['amount_missed'] += $loan->total_monthly_repayment;
                } elseif ($status === 'Settled') {
                    $this->summaryStats['settled']++;
                    $this->summaryStats['amount_collected'] += (float) $paid;
                }
            }
        }

        // Paginate the results
        $this->paginatedData = collect($this->recoveryData)
            ->forPage($this->page, $this->perPage)
            ->values()
            ->toArray();
    }

    public function exportData($format)
    {
        // Ensure data is up-to-date with current filters
        $this->loadData();

        $filename = 'recovery_' . now()->format('Ymd_His');

        $collection = collect($this->recoveryData);

        if ($format === 'excel') {
            return Excel::download(new GenericExport($collection), "$filename.xlsx");
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.recovery-pdf', ['data' => $collection]);
            return response()->streamDownload(
                fn () => print($pdf->output()), "$filename.pdf"
            );
        }

        if ($format === 'word') {
            $html = view('exports.recovery-pdf', ['data' => $collection])->render();
            $docPath = storage_path("app/public/$filename.doc");
            file_put_contents($docPath, $html);
            return response()->download($docPath)->deleteFileAfterSend();
        }

        // Handle unsupported formats
        abort(400, 'Unsupported export format.');
    }
}
