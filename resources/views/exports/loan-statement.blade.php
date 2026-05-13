<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loan Statement - {{ $loan->loan_id }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 13px;
            color: #333;
            line-height: 1.6;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            font-size: 16px;
            border-bottom: 1px solid #aaa;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: 50% 50%;
            gap: 8px 10px;
        }
        .item {
            margin-bottom: 4px;
        }
        .bold {
            font-weight: bold;
        }
        .bank-details ul {
            padding-left: 20px;
        }
        .bank-details li {
            margin-bottom: 3px;
        }
        .page-break {
            page-break-before: always;
        }
        .keep-together {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    {{-- LOGO HEADER --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <img src="https://zedfin.co/img/ZED-FIN_LOGO-removebg.png" alt="ZED-FIN Logo" height="80">
        <h2 style="margin-top: 10px;">Loan Statement</h2>
    </div>

    @php
        $summary = $statement['summary'];
        $borrower = $statement['borrower'];
        $history = $statement['history'];
        $missedInstallments = $statement['missed_installments'];
        $displayDob = filled($borrower?->dob ?: $loan->date_of_birth)
            ? \Carbon\Carbon::parse($borrower?->dob ?: $loan->date_of_birth)->format('d/m/Y')
            : '-';
    @endphp

    {{-- CLIENT INFO --}}
    <div class="section">
        <h2>Client Information</h2>
        <div class="grid">
            <div class="item"><span class="bold">Loan ID:</span> {{ $loan->loan_id }}</div>
            <div class="item"><span class="bold">Name:</span> {{ $borrower?->other_names ?: $borrower?->first_name ?: $loan->other_names }} {{ $borrower?->last_name ?: $loan->last_name }}</div>
            <div class="item"><span class="bold">NRC:</span> {{ $borrower?->identification ?: $loan->nrc }}</div>
            <div class="item"><span class="bold">Employee #:</span> {{ $loan->employee_no ?? '-' }}</div>
            <div class="item"><span class="bold">Employer:</span> {{ $borrower?->employer ?: $loan->employer ?? '-' }}</div>
            <div class="item"><span class="bold">Date of Birth:</span> {{ $displayDob }}</div>
        </div>
    </div>

    {{-- LOAN DETAILS --}}
    <div class="section">
        <h2>Loan Details</h2>
        <div class="grid">
            <div class="item"><span class="bold">Loan Amount:</span> ZMW {{ number_format($loan->principal_amount, 2) }}</div>
            <div class="item"><span class="bold">Interest Rate:</span> {{ $loan->interest_rate }}%</div>
            <div class="item"><span class="bold">Term:</span> {{ $loan->term_months ?? $loan->loan_duration ?? '-' }} months</div>
            <div class="item"><span class="bold">Loan Duration:</span> {{ number_format($loan->loan_duration) }}</div>
            <div class="item"><span class="bold">Start Date:</span> {{ optional($loan->loan_release_date)->format('d/m/Y') ?? '-' }}</div>
            <div class="item"><span class="bold">Maturity Date:</span> {{ optional($summary['maturity_date'])->format('d/m/Y') ?? '-' }}</div>
            <div class="item"><span class="bold">Monthly Repayment:</span> ZMW {{ number_format($summary['monthly_repayment'], 2) }}</div>
            <div class="item"><span class="bold">Outstanding Balance:</span> ZMW {{ number_format($summary['current_balance'], 2) }}</div>
            <div class="item"><span class="bold">Loan Type:</span> {{ $loan->loan_category ?? '-' }}</div>
            <div class="item"><span class="bold">Status:</span> {{ $loan->loan_status }}</div>
            <div class="item"><span class="bold">Repayments Made:</span> {{ $summary['repayments_made'] }}</div>
        </div>
    </div>

    {{-- REPAYMENT SUMMARY --}}
    <div class="section">
        <h2>Repayment Summary</h2>
        <div class="grid">
            <div class="item"><span class="bold">Total Paid:</span> ZMW {{ number_format($summary['total_paid'], 2) }}</div>
            <div class="item"><span class="bold">Principal Paid:</span> ZMW {{ number_format($summary['principal_paid'], 2) }}</div>
            <div class="item"><span class="bold">Interest Paid:</span> ZMW {{ number_format($summary['interest_paid'], 2) }}</div>
            <div class="item"><span class="bold">Insurance Paid:</span> ZMW {{ number_format($summary['insurance_paid'], 2) }}</div>
            <div class="item"><span class="bold">Interest Accrued To Date:</span> ZMW {{ number_format($summary['interest_accrued'], 2) }}</div>
            <div class="item"><span class="bold">Insurance Accrued To Date:</span> ZMW {{ number_format($summary['insurance_accrued'], 2) }}</div>
            <div class="item"><span class="bold">Unpaid Interest:</span> ZMW {{ number_format($summary['unpaid_interest'], 2) }}</div>
            <div class="item"><span class="bold">Unpaid Insurance:</span> ZMW {{ number_format($summary['unpaid_insurance'], 2) }}</div>
            <div class="item"><span class="bold">Amount Due Today:</span> ZMW {{ number_format($summary['due_today_amount'], 2) }}</div>
            <div class="item"><span class="bold">Missed / Overdue Amount:</span> ZMW {{ number_format($summary['missed_amount'], 2) }}</div>
            <div class="item"><span class="bold">Total Due Now:</span> ZMW {{ number_format($summary['today_and_arrears'], 2) }}</div>
            <div class="item"><span class="bold">Total Amount Due to Close Loan:</span> ZMW {{ number_format($summary['close_off_amount'], 2) }}</div>
            <div class="item"><span class="bold">Last Payment Date:</span> {{ optional($summary['last_payment_date'])->format('d/m/Y') ?? '-' }}</div>
            <div class="item"><span class="bold">Last Payment Amount:</span> ZMW {{ number_format($summary['last_payment_amount'], 2) }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Missed Payments</h2>
        @if($missedInstallments->isNotEmpty())
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Missed Date</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Due Month</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Expected</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Paid</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Shortfall</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($missedInstallments as $missed)
                        <tr>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">{{ optional($missed->missed_date)->format('d/m/Y') ?? optional($missed->due_month)->format('d/m/Y') }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">{{ optional($missed->due_month)->format('M Y') }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($missed->expected_amount, 2) }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($missed->paid_amount, 2) }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($missed->shortfall_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No missed payments recorded.</p>
        @endif
    </div>

    <div class="section">
        <h2>Repayment History</h2>
        @if($history->isNotEmpty())
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Month</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Total Paid</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Principal</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Interest</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Insurance</th>
                        <th style="text-align:left; border-bottom:1px solid #aaa; padding:6px 4px;">Payments</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($history as $row)
                        <tr>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">{{ $row['month'] }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($row['total_paid'], 2) }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($row['principal_paid'], 2) }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($row['interest_paid'], 2) }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">ZMW {{ number_format($row['insurance_paid'], 2) }}</td>
                            <td style="padding:6px 4px; border-bottom:1px solid #ddd;">{{ $row['payments_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No repayment history found.</p>
        @endif
    </div>

    <div class="page-break"></div>

    <div class="keep-together">
        {{-- BANK DETAILS --}}
        <div class="section bank-details">
            <h2>Payment Instructions</h2>
            <ul>
                <li><strong>Bank:</strong> ABSA BANK PLC</li>
                <li><strong>Account Name:</strong> ZED-FIN FINANCIAL SERVICES LIMITED</li>
                <li><strong>Account Number:</strong> 1197535</li>
                <li><strong>Branch:</strong> LUANSHYA</li>
                <li><strong>Sort Code:</strong> 13</li>
                <li><strong>SWIFT:</strong> BARCZMLX</li>
                <li><strong>Currency:</strong> ZMW</li>
                <li><strong>Note:</strong> Write your Loan ID and Name in the payment description, Statement is Vaild for 5 days from publish date below.</li>
            </ul>
        </div>

        {{-- ISSUED BY / STAMP --}}
        <hr style="margin: 40px 0;">

        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 60%;">
                <p><strong>Issued By:</strong> ________________________________________</p>
                <p><strong>Date:</strong> {{ now()->format('d/m/Y H:i') }}</p>
                <p><strong>Branch:</strong> ____________________________________________</p>
            </div>

            <div style="width: 30%; text-align: center; border: 1px dotted #ccc; height: 100px; display: flex; align-items: center; justify-content: center;">
                <span style="color: #ccc; font-weight: bold; font-size: 14px;">STAMP</span>
            </div>
        </div>
    </div>

</body>
</html>
