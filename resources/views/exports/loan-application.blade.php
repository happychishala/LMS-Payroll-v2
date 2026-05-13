<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loan Application</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.45; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { font-size: 22px; font-weight: 700; color: #0f172a; }
        .subtitle { font-size: 11px; color: #475569; margin-top: 4px; }
        .meta { margin-top: 8px; font-size: 11px; color: #334155; }
        .section { margin-top: 16px; }
        .section-title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .details td { border: 1px solid #cbd5e1; padding: 8px 10px; vertical-align: top; }
        .label { width: 28%; font-weight: 700; background: #f8fafc; }
        .value { width: 22%; }
        .full { width: 72%; }
        .terms { border: 1px solid #cbd5e1; padding: 12px; background: #f8fafc; }
        .terms p { margin: 0 0 8px; }
        .signature-table td { width: 50%; padding-top: 28px; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #334155; padding-top: 6px; margin-right: 20px; }
        .footer { margin-top: 20px; font-size: 10px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ config('app.name', 'Loan Management System') }}</div>
        <div class="subtitle">Loan Application Form</div>
        <div class="meta">
            Loan ID: {{ $loan->loan_id ?? $loan->loan_number ?? '-' }} |
            Generated: {{ $generatedAt->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Borrower Details</div>
        <table class="details">
            <tr>
                <td class="label">Full Name</td>
                <td class="value">{{ $borrower?->full_name ?: trim(collect([$borrower?->first_name ?: $borrower?->other_names, $borrower?->last_name])->filter()->implode(' ')) ?: '-' }}</td>
                <td class="label">Customer ID</td>
                <td class="value">{{ $borrower?->customer_id ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">NRC / ID</td>
                <td class="value">{{ $borrower?->identification ?? $loan->nrc ?? '-' }}</td>
                <td class="label">Employee Number</td>
                <td class="value">{{ $loan->employee_no ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Phone Number</td>
                <td class="value">{{ $borrower?->mobile ?? '-' }}</td>
                <td class="label">Email Address</td>
                <td class="value">{{ $borrower?->email ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Employer</td>
                <td class="full" colspan="3">{{ $borrower?->employer ?? $loan->employer ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Loan Details</div>
        <table class="details">
            <tr>
                <td class="label">Loan Type</td>
                <td class="value">{{ $loanType?->loan_name ?? $loan->loan_category ?? '-' }}</td>
                <td class="label">Loan Category</td>
                <td class="value">{{ $loan->loan_category ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Principal Amount</td>
                <td class="value">ZMW {{ number_format((float) ($loan->principal_amount ?? 0), 2) }}</td>
                <td class="label">Interest Rate</td>
                <td class="value">{{ number_format((float) ($loan->interest_rate ?? 0), 2) }}%</td>
            </tr>
            <tr>
                <td class="label">Loan Duration</td>
                <td class="value">{{ $loan->loan_duration ?? '-' }} {{ $loanType?->interest_cycle ?? 'month(s)' }}</td>
                <td class="label">Release Date</td>
                <td class="value">{{ optional($loan->loan_release_date)->format('Y-m-d') ?? $loan->loan_release_date ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Due Date</td>
                <td class="value">{{ $loan->loan_due_date ?? $loan->maturity_date ?? '-' }}</td>
                <td class="label">Monthly Repayment</td>
                <td class="value">ZMW {{ number_format((float) ($loan->total_monthly_repayment ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Repayment</td>
                <td class="value">ZMW {{ number_format((float) ($loan->repayment_amount ?? 0), 2) }}</td>
                <td class="label">Disbursement Amount</td>
                <td class="value">ZMW {{ number_format((float) ($loan->disbursement_amount ?? 0), 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Applicant Declaration</div>
        <div class="terms">
            <p>I confirm that the information provided in this application is true and complete to the best of my knowledge.</p>
            <p>I authorize {{ config('app.name', 'the lender') }} to review this application and any supporting documents submitted for credit assessment and loan processing purposes.</p>
            <p>I understand that this printed form must be signed by the customer and the signed copy uploaded back into the system before final processing is completed.</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Signatures</div>
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line">Customer Signature</div>
                </td>
                <td>
                    <div class="signature-line">Date</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="signature-line">Received By</div>
                </td>
                <td>
                    <div class="signature-line">Branch / Office</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This document was generated from the loan record for signing and upload.
    </div>
</body>
</html>
