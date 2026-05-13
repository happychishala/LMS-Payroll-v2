<!DOCTYPE html>
<html>
<head>
    <title>Amortization Schedule</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { margin-bottom: 20px; }
        .details { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: right; }
        th { background: #f0f0f0; }
        td:first-child, th:first-child { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Amortization Schedule for Loan: {{ $loan->loan_number ?? '-' }}</h2>
        <div class="details">
            <strong>Borrower:</strong>
            {{ trim(collect([$loan->borrower?->first_name ?: $loan->borrower?->other_names ?: $loan->other_names, $loan->borrower?->last_name ?: $loan->last_name])->filter()->implode(' ')) ?: '-' }}<br>
            <strong>Borrower ID:</strong> {{ $loan->borrower?->customer_id ?? $loan->borrower_id ?? '-' }}<br>
            <strong>Employee Number:</strong> {{ $loan->employee_no ?? '-' }}<br>
            <strong>Loan Type:</strong> {{ $loan->loan_type?->loan_name ?? $loan->loan_category ?? '-' }}<br>
            <strong>Principal:</strong> ZMW {{ number_format($loan->principal_amount ?? 0, 2) }}<br>
            <strong>Interest Rate:</strong> {{ $loan->interest_rate ?? '-' }}%<br>
            <strong>Term:</strong> {{ $loan->loan_duration ?? '-' }} months<br>
            <strong>First Payment Date:</strong> {{ $firstPaymentDate ?? '-' }}<br>
            <strong>Stored Monthly Repayment:</strong> ZMW {{ number_format($loan->total_monthly_repayment ?? 0, 2) }}<br>
            <strong>Monthly Insurance:</strong> ZMW {{ number_format($loan->monthly_insurance ?? 0, 2) }}
        </div>
    </div>
    @if(!empty($summary))
        <div class="details">
            <strong>Total Payment:</strong> ZMW {{ number_format($summary['total_payment'] ?? 0, 2) }}<br>
            <strong>Total Principal:</strong> ZMW {{ number_format($summary['total_principal'] ?? 0, 2) }}<br>
            <strong>Total Interest:</strong> ZMW {{ number_format($summary['total_interest'] ?? 0, 2) }}<br>
            <strong>Total Insurance:</strong> ZMW {{ number_format($summary['total_insurance'] ?? 0, 2) }}<br>
            <strong>Ending Balance:</strong> ZMW {{ number_format($summary['ending_balance'] ?? 0, 2) }}<br>
            <strong>Expected Total Recoverable:</strong> ZMW {{ number_format($summary['expected_total_recoverable'] ?? 0, 2) }}<br>
            <strong>Actual Paid:</strong> ZMW {{ number_format($summary['actual_total_paid'] ?? 0, 2) }}<br>
            <strong>Actual Principal Paid:</strong> ZMW {{ number_format($summary['actual_principal_paid'] ?? 0, 2) }}<br>
            <strong>Actual Interest Paid:</strong> ZMW {{ number_format($summary['actual_interest_paid'] ?? 0, 2) }}<br>
            <strong>Actual Insurance Paid:</strong> ZMW {{ number_format($summary['actual_insurance_paid'] ?? 0, 2) }}<br>
            <strong>Interest Arrears:</strong> ZMW {{ number_format($summary['actual_interest_arrears'] ?? 0, 2) }}<br>
            <strong>Insurance Arrears:</strong> ZMW {{ number_format($summary['actual_insurance_arrears'] ?? 0, 2) }}<br>
            <strong>Actual Ending Balance:</strong> ZMW {{ number_format($summary['actual_ending_balance'] ?? 0, 2) }}<br>
            <strong>Actual Total Recoverable:</strong> ZMW {{ number_format($summary['actual_total_recoverable'] ?? 0, 2) }}
        </div>
    @endif
    @if(!empty($schedule))
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Date</th>
                <th>Payment</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Insurance</th>
                <th>Sanlam</th>
                <th>ZedFin</th>
                <th>Scheduled Balance</th>
                <th>Actual Paid</th>
                <th>Actual Principal</th>
                <th>Actual Interest</th>
                <th>Actual Insurance</th>
                <th>Interest Arrears</th>
                <th>Insurance Arrears</th>
                <th>Expected Recoverable</th>
                <th>Actual Recoverable</th>
                <th>Actual Balance</th>
                <th>Variance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedule as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>ZMW {{ number_format($row['payment'], 2) }}</td>
                    <td>ZMW {{ number_format($row['principal'], 2) }}</td>
                    <td>ZMW {{ number_format($row['interest'], 2) }}</td>
                    <td>ZMW {{ number_format($row['insurance'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['sanlam'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['zedfin'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['balance'], 2) }}</td>
                    <td>ZMW {{ number_format($row['actual_payment'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['actual_principal_paid'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['actual_interest_paid'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['actual_insurance_paid'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['interest_arrears'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['insurance_arrears'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['expected_total_recoverable'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['actual_total_recoverable'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['actual_balance'] ?? 0, 2) }}</td>
                    <td>ZMW {{ number_format($row['balance_variance'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p>No amortization data available.</p>
    @endif
</body>
</html>
