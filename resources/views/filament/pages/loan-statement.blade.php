<x-filament::page>
    @php
        $summary = $statement['summary'];
        $borrower = $statement['borrower'];
        $history = $statement['history'];
        $missedInstallments = $statement['missed_installments'];
        $displayDob = filled($borrower?->dob ?: $loan->date_of_birth)
            ? \Carbon\Carbon::parse($borrower?->dob ?: $loan->date_of_birth)->format('d/m/Y')
            : '-';
    @endphp

    <div class="space-y-6">

        {{-- Loan Selector & Export --}}
        <form method="GET" action="{{ route('filament.admin.pages.loan-statement') }}"
              class="bg-white dark:bg-gray-800 shadow rounded-xl p-4 flex items-center justify-between">
            <div class="w-1/2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Search Loan ID
                </label>
                <div class="flex items-center gap-3">
                    <input
                        type="text"
                        name="loan_id"
                        value="{{ $loanId }}"
                        placeholder="Enter loan ID, e.g. L001"
                        class="w-full rounded-lg border-gray-300 shadow-sm dark:bg-gray-700 dark:text-white dark:border-gray-600"
                    />
                    <x-filament::button type="submit">
                        Search
                    </x-filament::button>
                </div>
            </div>
<x-filament::button
    color="warning"
    tag="a"
    href="{{ route('loan.statement.export', ['loan_id' => $loan->loan_id]) }}"
>
    Export to PDF
</x-filament::button>

        </form>



    {{-- CLIENT INFO --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Client Information</h2>
        @if($loan)
            <div class="grid grid-cols-2 gap-4 text-sm text-gray-700 dark:text-white">
                <div><strong>Loan ID:</strong> {{ $loan->loan_id }}</div>
                <div><strong>Name:</strong> {{ $borrower?->other_names ?: $borrower?->first_name ?: $loan->other_names }} {{ $borrower?->last_name ?: $loan->last_name }}</div>
                <div><strong>NRC:</strong> {{ $borrower?->identification ?: $loan->nrc }}</div>
                <div><strong>Employee #:</strong> {{ $loan->employee_no ?? '-' }}</div>
                <div><strong>Employer:</strong> {{ $borrower?->employer ?: $loan->employer ?? '-' }}</div>
                <div><strong>Date of Birth:</strong> {{ $displayDob }}</div>
            </div>
        @else
            <p class="text-red-500 text-sm">No borrower info found for this loan.</p>
        @endif
    </div>

    {{-- LOAN DETAILS --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Loan Details</h2>
        @if($loan)
            <div class="grid grid-cols-2 gap-4 text-sm text-gray-700 dark:text-white">
                <div><strong>Loan ID:</strong> {{ $loan->loan_id }}</div>
                <div><strong>Loan Amount:</strong> ZMW {{ number_format($loan->principal_amount, 2) }}</div>
                <div><strong>Interest Rate:</strong> {{ $loan->interest_rate }}%</div>
                <div><strong>Term:</strong> {{ $loan->term_months ?? $loan->loan_duration ?? '-' }} months</div>
                <div class="item"> <strong><span class="bold">Loan Duration:</span></strong> {{ number_format($loan->loan_duration) }}</div>
                <div><strong>Start Date:</strong> {{ optional($loan->loan_release_date)->format('d/m/Y') ?? '-' }}</div>
                <div><strong>Maturity Date:</strong> {{ optional($summary['maturity_date'])->format('d/m/Y') ?? '-' }}</div>
                <div><strong>Monthly Repayment:</strong> ZMW {{ number_format($summary['monthly_repayment'], 2) }}</div>
                <div><strong>Outstanding Balance:</strong> ZMW {{ number_format($summary['current_balance'], 2) }}</div>
                <div><strong>Loan Type:</strong> {{ $loan->loan_category ?? '-' }}</div>
                <div><strong>Status:</strong> {{ $loan->loan_status }}</div>
                <div><strong>Repayments Made:</strong> {{ $summary['repayments_made'] }}</div>
            </div>
        @endif
    </div>

    {{-- REPAYMENT SUMMARY --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Repayment Summary</h2>

<div class="grid grid-cols-2 gap-4 text-sm text-gray-700 dark:text-white">
    <div><strong>Total Paid:</strong> ZMW {{ number_format($summary['total_paid'], 2) }}</div>
    <div><strong>Principal Paid:</strong> ZMW {{ number_format($summary['principal_paid'], 2) }}</div>
    <div><strong>Interest Paid:</strong> ZMW {{ number_format($summary['interest_paid'], 2) }}</div>
    <div><strong>Insurance Paid:</strong> ZMW {{ number_format($summary['insurance_paid'], 2) }}</div>
    <div><strong>Interest Accrued To Date:</strong> ZMW {{ number_format($summary['interest_accrued'], 2) }}</div>
    <div><strong>Insurance Accrued To Date:</strong> ZMW {{ number_format($summary['insurance_accrued'], 2) }}</div>
    <div><strong>Unpaid Interest:</strong> ZMW {{ number_format($summary['unpaid_interest'], 2) }}</div>
    <div><strong>Unpaid Insurance:</strong> ZMW {{ number_format($summary['unpaid_insurance'], 2) }}</div>
    <div><strong>Amount Due Today:</strong> ZMW {{ number_format($summary['due_today_amount'], 2) }}</div>
    <div><strong>Missed / Overdue Amount:</strong> ZMW {{ number_format($summary['missed_amount'], 2) }}</div>
    <div class="col-span-2"><strong>Total Due Now:</strong> ZMW {{ number_format($summary['today_and_arrears'], 2) }}</div>
    <div class="col-span-2"><strong>Total Amount Due to Close Loan:</strong> ZMW {{ number_format($summary['close_off_amount'], 2) }}</div>
    <div><strong>Last Payment Date:</strong> {{ optional($summary['last_payment_date'])->format('d/m/Y') ?? '-' }}</div>
    <div><strong>Last Payment Amount:</strong> ZMW {{ number_format($summary['last_payment_amount'], 2) }}</div>
</div>

    </div>

    {{-- MISSED PAYMENTS --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Missed Payments</h2>
        @if($missedInstallments->isNotEmpty())
            <div class="overflow-auto">
                <table class="min-w-full text-sm text-left text-gray-700 dark:text-white">
                    <thead>
                        <tr>
                            <th class="py-2 pr-4">Missed Date</th>
                            <th class="py-2 pr-4">Due Month</th>
                            <th class="py-2 pr-4">Expected</th>
                            <th class="py-2 pr-4">Paid</th>
                            <th class="py-2 pr-4">Shortfall</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($missedInstallments as $missed)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="py-2 pr-4">{{ optional($missed->missed_date)->format('d/m/Y') ?? optional($missed->due_month)->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ optional($missed->due_month)->format('M Y') }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($missed->expected_amount, 2) }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($missed->paid_amount, 2) }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($missed->shortfall_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No missed payments recorded.</p>
        @endif
    </div>

    {{-- REPAYMENT HISTORY --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Repayment History</h2>
        @if($history->isNotEmpty())
            <div class="overflow-auto">
                <table class="min-w-full text-sm text-left text-gray-700 dark:text-white">
                    <thead>
                        <tr>
                            <th class="py-2 pr-4">Month</th>
                            <th class="py-2 pr-4">Total Paid</th>
                            <th class="py-2 pr-4">Principal</th>
                            <th class="py-2 pr-4">Interest</th>
                            <th class="py-2 pr-4">Insurance</th>
                            <th class="py-2 pr-4">Payments</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $row)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="py-2 pr-4">{{ $row['month'] }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($row['total_paid'], 2) }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($row['principal_paid'], 2) }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($row['interest_paid'], 2) }}</td>
                                <td class="py-2 pr-4">ZMW {{ number_format($row['insurance_paid'], 2) }}</td>
                                <td class="py-2 pr-4">{{ $row['payments_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No repayment history found.</p>
        @endif
    </div>

    {{-- BANK DETAILS --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Payment Instructions</h2>
        <ul class="text-sm text-gray-700 dark:text-white list-disc pl-5 space-y-1">
            <li>Bank: ABSA BANK PLC</li>
            <li>Account Name: ZED-FIN FINANCIAL SERVICES LIMITED</li>
            <li>Account Number: 1197535</li>
            <li>Branch: LUANSHYA</li>
            <li>Sort Code: 13</li>
            <li>SWIFT: BARCZMLX</li>
            <li>Currency: ZMW</li>
            <li><strong>Note:</strong> Write your Loan ID and Name in the payment description, Statement is Vaild for 5 days from publish date</li>
        </ul>
    </div>

</div>

</x-filament::page>
