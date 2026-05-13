<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Exports\EmployerCsvExport;
use App\Models\LoanType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EmployerCsvReportController extends Controller
{
    /**
     * Display the filter form.
     */
    public function index()
    {
        $loanNames = LoanType::query()
            ->orderBy('loan_name')
            ->pluck('loan_name');

        return view('reports.employer_csv', compact('loanNames'));
    }

    /**
     * Export filtered report as CSV.
     */
    public function export(Request $request)
    {
        $request->validate([
            'loan_name' => 'required|string',
            'month' => 'required|date_format:Y-m',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return Excel::download(
            new EmployerCsvExport(
                $request->string('loan_name')->toString(),
                $request->string('month')->toString(),
                $request->date('start_date')?->toDateString(),
                $request->date('end_date')?->toDateString()
            ),
            'employer_report_' . Str::slug($request->string('loan_name')->toString(), '_') . '_' . $request->month . '.csv'
        );
    }
}
