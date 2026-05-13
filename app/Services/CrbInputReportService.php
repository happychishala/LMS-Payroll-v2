<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Repayments;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CrbInputReportService
{
    public function __construct(
        protected AddressGeocodingService $addressGeocodingService,
    ) {}

    public const HEADINGS = [
        'Salutation',
        'Surname',
        'Forename 1',
        'Forename or Initial 2',
        'Forename or Initial 3',
        'NRC Number',
        'Passport No',
        'Alien ID',
        'Nationality',
        'Tax No / TPIN No',
        'Driving License No',
        'Social Security Number',
        'Health Insurance Number / NAPSA Number',
        'Marital Status',
        'No of Dependants',
        'Gender / Sex',
        'Date of Birth',
        'Place Of Birth',
        'Postal Number',
        'Postal Code',
        'Town',
        'Country',
        'Email Address',
        'Province',
        'District',
        'Plot Number',
        'Physical Address Line 1',
        'Physical Address Line 2',
        'Residence Type / House Type',
        'Duration at this Address (Years)',
        'Duration at this address (Months)',
        'Work Telephone',
        'Home Telephone',
        'Mobile Telephone',
        'Facsimile / Fax',
        'Employer Name',
        'Employer Address Line 1',
        'Employer Address Line 2',
        'Employer Town',
        'Employer Country',
        'Occupation/ Designation',
        'Employment Duration (Years)',
        'Employment Duration (Months)',
        'Income',
        'Income Frequency',
        'Group Name',
        'Group Number',
        'Delinquency Date',
        'Account Number',
        'Old Account Number',
        'Account Type',
        'Account Status',
        'Account Status Change Date',
        'BOZ Classification',
        'Overdraft Type',
        'Grace Period',
        'Account Owner',
        'Number of Joint Loan Participants',
        'Reporting Currency ',
        'Date Loan(credit facility) Account Opened',
        'Date Account Updated',
        'Terms Duration / Payment Terms',
        'Account Repayment Term',
        'Opening Balance / Credit Limit / Principal ',
        'Amount paid to date',
        'Current Balance',
        'Available Credit',
        'Scheduled Payment Amount',
        'Actual Payment Amount',
        'Amount Past Due',
        'Installment(s) in Arrears',
        'Days in Arrears',
        'Date Closed',
        'Closure Reason',
        'Last Payment Date',
        'Last Payment Amount',
        'Interest Rate at Disbursement',
        'First Payment Date',
        'Approved Amount',
        'Disbursed Amount',
        'Approval Date',
        'Maturity Date',
        'Interest Type',
        'Interest Calculation Method',
        'Credit Amortization Type',
    ];

    public function build(string $reportDate, ?string $employer = null, bool $includeRecordsWithIssues = false): Collection
    {
        return $this->report($reportDate, $employer, $includeRecordsWithIssues)['rows'];
    }

    public function report(string $reportDate, ?string $employer = null, bool $includeRecordsWithIssues = false): array
    {
        $asOfDate = Carbon::parse($reportDate)->endOfDay();
        $loans = Loan::query()
            ->with(['repaymentSchedules', 'statusReason', 'borrower'])
            ->whereDate('loan_release_date', '<=', $asOfDate->toDateString())
            ->whereNotIn('loan_status', ['requested', 'processing', 'denied'])
            ->when($employer, function ($query, $employer) {
                $query->where(function ($inner) use ($employer) {
                    $inner->where('employer', $employer)
                        ->orWhere('loan_category', $employer);
                });
            })
            ->orderBy('loan_release_date')
            ->orderBy('loan_id')
            ->get();

        $repaymentsByLoan = $this->repaymentMapForLoans($loans, $asOfDate);

        $records = $loans
            ->map(fn (Loan $loan) => $this->buildRecord(
                $loan,
                $asOfDate,
                $repaymentsByLoan[$this->repaymentMapKey($loan)] ?? collect()
            ))
            ->values();

        $rows = $records
            ->when(! $includeRecordsWithIssues, fn (Collection $collection) => $collection->where('is_valid', true))
            ->pluck('row')
            ->values();

        $issues = $records
            ->flatMap(function (array $record) {
                return collect($record['issues'])->map(function (array $issue) use ($record) {
                    return [
                        'loan_id' => $record['loan_id'],
                        'borrower_name' => $record['borrower_name'],
                        'severity' => $issue['severity'],
                        'field' => $issue['field'],
                        'message' => $issue['message'],
                    ];
                });
            })
            ->values();

        return [
            'rows' => $rows,
            'records' => $records,
            'issues' => $issues,
            'summary' => [
                'total_loans' => $records->count(),
                'valid_loans' => $records->where('is_valid', true)->count(),
                'blocked_loans' => $records->where('is_valid', false)->count(),
                'error_count' => $issues->where('severity', 'error')->count(),
                'warning_count' => $issues->where('severity', 'warning')->count(),
                'exportable_rows' => $rows->count(),
            ],
        ];
    }

    protected function buildRecord(Loan $loan, Carbon $asOfDate, Collection $repayments): array
    {
        $borrower = $loan->borrower ?: $this->resolveBorrower($loan);
        $forenames = $this->splitForenames($borrower, $loan);
        $lastPayment = $repayments
            ->sortBy(fn ($repayment) => $this->safeDate($repayment->receipt_date ?? $repayment->payment_date)?->timestamp ?? 0)
            ->last();

        $loanOpenedDate = $this->safeDate($loan->loan_release_date);
        $firstPaymentDate = $this->firstPaymentDate($loan, $loanOpenedDate);
        $maturityDate = $this->maturityDate($loan, $firstPaymentDate, $loanOpenedDate);
        $scheduledPayment = $this->decimal($loan->total_monthly_repayment ?: $loan->payment);
        $totalPaid = $this->decimal($repayments->sum(fn ($repayment) => $this->repaymentAmount($repayment)));
        $currentBalance = $this->decimal($loan->balance);

        if ($currentBalance === 0.0 && $loan->principal_amount) {
            $currentBalance = $this->decimal(max((float) $loan->principal_amount - $totalPaid, 0));
        }

        [$amountPastDue, $installmentsInArrears, $daysInArrears, $delinquencyDate] = $this->delinquencyMetrics(
            $loan,
            $asOfDate,
            $scheduledPayment,
            $firstPaymentDate,
            $repayments
        );

        $isClosed = $this->isClosedLoan($loan, $currentBalance, $asOfDate);
        $dateClosed = $isClosed ? ($this->safeDate($loan->closed_at) ?: $this->safeDate(optional($lastPayment)->receipt_date)) : null;
        $accountStatus = $isClosed ? 'C' : 'A';
        $closureReason = $isClosed ? ($loan->statusReason->code ?? 'N') : '';

        $employerName = $borrower?->employer ?? $loan->employer ?? '';
        $employerAddress = trim((string) ($borrower?->employer_address ?? ''));
        $residentialAddress = $this->parseAddressComponents(
            (string) ($borrower?->address ?? ''),
            (string) ($borrower?->city ?? ''),
            (string) ($borrower?->province ?? '')
        );
        $workAddress = $this->parseAddressComponents(
            $employerAddress,
            $residentialAddress['town'],
            $residentialAddress['province']
        );

        $row = [
            'Salutation' => $this->salutation($borrower, $loan),
            'Surname' => (string) ($borrower->last_name ?? $loan->last_name ?? ''),
            'Forename 1' => $forenames[0],
            'Forename or Initial 2' => $forenames[1],
            'Forename or Initial 3' => $forenames[2],
            'NRC Number' => (string) ($borrower->identification ?? $loan->nrc ?? ''),
            'Passport No' => '',
            'Alien ID' => '',
            'Nationality' => (string) ($borrower->nationality ?? 'Zambian'),
            'Tax No / TPIN No' => '',
            'Driving License No' => '',
            'Social Security Number' => '',
            'Health Insurance Number / NAPSA Number' => '',
            'Marital Status' => $this->mapMaritalStatus($borrower->marital_status ?? null),
            'No of Dependants' => '',
            'Gender / Sex' => $this->mapGender($borrower->gender ?? $loan->gender ?? null),
            'Date of Birth' => $this->formatCrbDate($borrower->dob ?? $loan->date_of_birth ?? null),
            'Place Of Birth' => '',
            'Postal Number' => '',
            'Postal Code' => (string) ($borrower->zipcode ?? ''),
            'Town' => $residentialAddress['town'],
            'Country' => $residentialAddress['country'],
            'Email Address' => (string) ($borrower->email ?? ''),
            'Province' => $residentialAddress['province'],
            'District' => $residentialAddress['district'],
            'Plot Number' => $residentialAddress['plot_number'],
            'Physical Address Line 1' => $residentialAddress['line_1'],
            'Physical Address Line 2' => $residentialAddress['line_2'],
            'Residence Type / House Type' => '',
            'Duration at this Address (Years)' => '',
            'Duration at this address (Months)' => '',
            'Work Telephone' => '',
            'Home Telephone' => '',
            'Mobile Telephone' => (string) ($borrower->mobile ?? ''),
            'Facsimile / Fax' => '',
            'Employer Name' => $employerName,
            'Employer Address Line 1' => $workAddress['line_1'],
            'Employer Address Line 2' => $workAddress['line_2'],
            'Employer Town' => $workAddress['town'],
            'Employer Country' => $workAddress['country'],
            'Occupation/ Designation' => (string) ($borrower->employer_position ?? $borrower->occupation ?? ''),
            'Employment Duration (Years)' => '',
            'Employment Duration (Months)' => '',
            'Income' => '',
            'Income Frequency' => '',
            'Group Name' => '',
            'Group Number' => (string) ($borrower->employer_number ?? ''),
            'Delinquency Date' => $this->formatCrbDate($delinquencyDate),
            'Account Number' => (string) ($loan->loan_id ?? ''),
            'Old Account Number' => '',
            'Account Type' => 'I',
            'Account Status' => $accountStatus,
            'Account Status Change Date' => $this->formatCrbDate($dateClosed),
            'BOZ Classification' => 'P',
            'Overdraft Type' => '',
            'Grace Period' => '',
            'Account Owner' => 'O',
            'Number of Joint Loan Participants' => 0,
            'Reporting Currency ' => 'ZMW',
            'Date Loan(credit facility) Account Opened' => $this->formatCrbDate($loanOpenedDate),
            'Date Account Updated' => $this->formatCrbDate($asOfDate),
            'Terms Duration / Payment Terms' => (string) ($loan->loan_duration ?? $loan->term_months ?? ''),
            'Account Repayment Term' => 'MTH',
            'Opening Balance / Credit Limit / Principal ' => $this->decimal($loan->principal_amount),
            'Amount paid to date' => $totalPaid,
            'Current Balance' => $currentBalance,
            'Available Credit' => '',
            'Scheduled Payment Amount' => $scheduledPayment,
            'Actual Payment Amount' => $totalPaid,
            'Amount Past Due' => $amountPastDue,
            'Installment(s) in Arrears' => $installmentsInArrears,
            'Days in Arrears' => $daysInArrears,
            'Date Closed' => $this->formatCrbDate($dateClosed),
            'Closure Reason' => $closureReason,
            'Last Payment Date' => $this->formatCrbDate(optional($lastPayment)->receipt_date ?? optional($lastPayment)->payment_date),
            'Last Payment Amount' => $this->decimal($lastPayment ? $this->repaymentAmount($lastPayment) : 0),
            'Interest Rate at Disbursement' => $this->decimal($loan->interest_rate),
            'First Payment Date' => $this->formatCrbDate($firstPaymentDate),
            'Approved Amount' => $this->decimal($loan->principal_amount),
            'Disbursed Amount' => $this->decimal($loan->final_disbursement_amount ?? $loan->disbursement_amount),
            'Approval Date' => $this->formatCrbDate($loanOpenedDate),
            'Maturity Date' => $this->formatCrbDate($maturityDate),
            'Interest Type' => 'A',
            'Interest Calculation Method' => 'A',
            'Credit Amortization Type' => 'A',
        ];

        $issues = $this->validateRecord($loan, $borrower, $row, $scheduledPayment, $currentBalance, $isClosed);

        return [
            'loan_id' => (string) ($loan->loan_id ?? ''),
            'borrower_name' => trim(collect([$row['Forename 1'], $row['Forename or Initial 2'], $row['Forename or Initial 3'], $row['Surname']])->filter()->implode(' ')),
            'row' => $row,
            'issues' => $issues,
            'is_valid' => collect($issues)->where('severity', 'error')->isEmpty(),
        ];
    }

    protected function validateRecord(Loan $loan, ?Borrower $borrower, array $row, float $scheduledPayment, float $currentBalance, bool $isClosed): array
    {
        $issues = [];

        if (! $borrower) {
            $issues[] = $this->issue('error', 'borrower', 'Borrower/customer details record not found for this loan.');
        }

        if ($row['Surname'] === '') {
            $issues[] = $this->issue('error', 'surname', 'Surname is missing.');
        }

        if ($row['Forename 1'] === '') {
            $issues[] = $this->issue('error', 'forename_1', 'Primary forename is missing.');
        }

        if ($row['NRC Number'] === '') {
            $issues[] = $this->issue('error', 'nrc', 'NRC number is missing.');
        }

        if ($row['Date of Birth'] === '') {
            $issues[] = $this->issue('error', 'date_of_birth', 'Date of birth is missing.');
        }

        if ($row['Gender / Sex'] === '') {
            $issues[] = $this->issue('error', 'gender', 'Gender is missing.');
        }

        if ($row['Physical Address Line 1'] === '') {
            $issues[] = $this->issue('warning', 'address', 'Physical address is missing from customer details.');
        }

        if ($row['Mobile Telephone'] === '') {
            $issues[] = $this->issue('warning', 'mobile', 'Mobile number is missing from customer details.');
        }

        if ($row['Employer Name'] === '') {
            $issues[] = $this->issue('error', 'employer', 'Employer name is missing.');
        }

        if ($row['Account Number'] === '') {
            $issues[] = $this->issue('error', 'account_number', 'Loan/account number is missing.');
        }

        if ($row['Date Loan(credit facility) Account Opened'] === '') {
            $issues[] = $this->issue('error', 'loan_release_date', 'Loan release date is missing.');
        }

        if ((float) $row['Opening Balance / Credit Limit / Principal '] <= 0) {
            $issues[] = $this->issue('error', 'principal_amount', 'Principal amount must be greater than zero.');
        }

        if ($scheduledPayment <= 0) {
            $issues[] = $this->issue('error', 'scheduled_payment', 'Scheduled payment amount must be greater than zero.');
        }

        if ($currentBalance < 0) {
            $issues[] = $this->issue('warning', 'current_balance', 'Current balance is negative.');
        }

        if ($isClosed && $row['Date Closed'] === '') {
            $issues[] = $this->issue('warning', 'date_closed', 'Loan appears closed but date closed could not be determined.');
        }

        if ($isClosed && $row['Closure Reason'] === '') {
            $issues[] = $this->issue('warning', 'closure_reason', 'Loan appears closed but closure reason is missing.');
        }

        if ($borrower) {
            $this->appendCustomerDetailChecks($issues, $loan, $borrower, $row);
        }

        return $issues;
    }

    protected function appendCustomerDetailChecks(array &$issues, Loan $loan, Borrower $borrower, array $row): void
    {
        $loanNrc = trim((string) ($loan->nrc ?? ''));
        $borrowerNrc = trim((string) ($borrower->identification ?? ''));

        if ($loanNrc !== '' && $borrowerNrc !== '' && strcasecmp($loanNrc, $borrowerNrc) !== 0) {
            $issues[] = $this->issue('error', 'nrc_mismatch', "Loan NRC '{$loanNrc}' does not match customer details NRC '{$borrowerNrc}'.");
        }

        $loanDob = $this->formatCrbDate($loan->date_of_birth ?? null);
        $borrowerDob = $this->formatCrbDate($borrower->dob ?? null);

        if ($loanDob !== '' && $borrowerDob !== '' && $loanDob !== $borrowerDob) {
            $issues[] = $this->issue('error', 'dob_mismatch', "Loan DOB '{$loanDob}' does not match customer details DOB '{$borrowerDob}'.");
        }

        $loanGender = $this->mapGender($loan->gender ?? null);
        $borrowerGender = $this->mapGender($borrower->gender ?? null);

        if ($loanGender !== '' && $borrowerGender !== '' && $loanGender !== $borrowerGender) {
            $issues[] = $this->issue('error', 'gender_mismatch', "Loan gender '{$loanGender}' does not match customer details gender '{$borrowerGender}'.");
        }

        $loanEmployer = trim((string) ($loan->employer ?? ''));
        $borrowerEmployer = trim((string) ($borrower->employer ?? ''));

        if ($loanEmployer !== '' && $borrowerEmployer !== '' && strcasecmp($loanEmployer, $borrowerEmployer) !== 0) {
            $issues[] = $this->issue('warning', 'employer_mismatch', "Loan employer '{$loanEmployer}' does not match customer details employer '{$borrowerEmployer}'.");
        }

        if ($row['Employer Name'] === '' && $borrowerEmployer !== '') {
            $issues[] = $this->issue('error', 'employer_mapping', 'Employer exists in customer details but was not mapped into the CRB row.');
        }
    }

    protected function issue(string $severity, string $field, string $message): array
    {
        return [
            'severity' => $severity,
            'field' => $field,
            'message' => $message,
        ];
    }

    protected function resolveBorrower(Loan $loan): ?Borrower
    {
        return $loan->borrower
            ?: Borrower::query()->find($loan->borrower_id)
            ?? Borrower::query()->where('customer_id', $loan->borrower_id)->first();
    }

    protected function splitForenames(?Borrower $borrower, Loan $loan): array
    {
        $names = trim((string) (
            $borrower?->other_names
            ?: $loan->other_names
            ?: $borrower?->first_name
            ?: ''
        ));

        $parts = preg_split('/\s+/', $names ?: '', 3) ?: [];
        $parts = array_values(array_filter($parts, fn ($value) => $value !== ''));

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
            $parts[2] ?? '',
        ];
    }

    protected function delinquencyMetrics(Loan $loan, Carbon $asOfDate, float $scheduledPayment, ?Carbon $firstPaymentDate, Collection $repayments): array
    {
        $dueSchedules = $loan->repaymentSchedules
            ->filter(fn ($schedule) => $this->safeDate($schedule->due_date)?->lte($asOfDate))
            ->sortBy(fn ($schedule) => $this->safeDate($schedule->due_date)?->timestamp ?? 0)
            ->values();

        $expectedAmount = $dueSchedules->sum(fn ($schedule) => (float) ($schedule->monthly_payment ?? 0));

        if ($expectedAmount <= 0 && $scheduledPayment > 0 && $firstPaymentDate) {
            $monthsDue = max(0, $firstPaymentDate->copy()->startOfDay()->diffInMonths($asOfDate->copy()->startOfDay(), false) + 1);

            if ($firstPaymentDate->gt($asOfDate)) {
                $monthsDue = 0;
            }

            $expectedAmount = $monthsDue * $scheduledPayment;
        }

        $paid = $repayments->sum(fn ($repayment) => $this->repaymentAmount($repayment));

        $amountPastDue = $this->decimal(max($expectedAmount - (float) $paid, 0));
        $installmentsInArrears = $scheduledPayment > 0 ? (int) floor($amountPastDue / $scheduledPayment) : 0;

        $earliestDelinquentDate = null;

        if ($amountPastDue > 0 && $dueSchedules->isNotEmpty()) {
            $earliestDelinquentDate = $dueSchedules
                ->first(fn ($schedule) => in_array($schedule->payment_status, ['Unpaid', 'Partial'], true))
                ?->due_date;
        }

        if (! $earliestDelinquentDate && $amountPastDue > 0) {
            $earliestDelinquentDate = $firstPaymentDate;
        }

        $daysInArrears = $earliestDelinquentDate
            ? max($this->safeDate($earliestDelinquentDate)?->startOfDay()->diffInDays($asOfDate->copy()->startOfDay(), false) ?? 0, 0)
            : 0;

        return [
            $amountPastDue,
            $installmentsInArrears,
            $daysInArrears,
            $this->safeDate($earliestDelinquentDate),
        ];
    }

    protected function firstPaymentDate(Loan $loan, ?Carbon $loanOpenedDate): ?Carbon
    {
        $scheduleDate = $loan->repaymentSchedules
            ->map(fn ($schedule) => $this->safeDate($schedule->due_date))
            ->filter()
            ->sortBy->timestamp
            ->first();

        if ($scheduleDate) {
            return $scheduleDate;
        }

        if ($loan->first_repayment_date) {
            return $this->safeDate($loan->first_repayment_date);
        }

        return $loanOpenedDate?->copy()->addMonth();
    }

    protected function maturityDate(Loan $loan, ?Carbon $firstPaymentDate, ?Carbon $loanOpenedDate): ?Carbon
    {
        $scheduleMaturity = $loan->repaymentSchedules
            ->map(fn ($schedule) => $this->safeDate($schedule->due_date))
            ->filter()
            ->sortByDesc->timestamp
            ->first();

        if ($scheduleMaturity) {
            return $scheduleMaturity;
        }

        if ($loan->maturity_date) {
            return $this->safeDate($loan->maturity_date);
        }

        $duration = (int) ($loan->loan_duration ?? $loan->term_months ?? 0);

        if ($firstPaymentDate && $duration > 0) {
            return $firstPaymentDate->copy()->addMonths($duration - 1);
        }

        return $loanOpenedDate && $duration > 0 ? $loanOpenedDate->copy()->addMonths($duration) : null;
    }

    protected function isClosedLoan(Loan $loan, float $currentBalance, Carbon $asOfDate): bool
    {
        $status = Str::lower(trim((string) $loan->loan_status));

        if (in_array($status, ['paid off', 'paid_off', 'settled', 'closed'], true)) {
            return true;
        }

        return $currentBalance <= 0
            && $this->safeDate($this->maturityDate($loan, $this->firstPaymentDate($loan, $this->safeDate($loan->loan_release_date)), $this->safeDate($loan->loan_release_date)))?->lte($asOfDate);
    }

    protected function salutation(?Borrower $borrower, Loan $loan): string
    {
        $title = trim((string) ($borrower->title ?? ''));

        if ($title !== '') {
            return $title;
        }

        return match ($this->mapGender($borrower->gender ?? $loan->gender ?? null)) {
            'M' => 'Mr',
            'F' => 'Ms',
            default => '',
        };
    }

    protected function mapGender(?string $gender): string
    {
        return match (Str::lower(trim((string) $gender))) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            default => '',
        };
    }

    protected function mapMaritalStatus(?string $status): string
    {
        return match (Str::lower(trim((string) $status))) {
            'married' => 'M',
            'single' => 'S',
            'divorced' => 'D',
            'widowed' => 'W',
            default => '',
        };
    }

    protected function formatCrbDate($value): string
    {
        $date = $this->safeDate($value);

        return $date ? $date->format('dmY') : '';
    }

    protected function safeDate($value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    protected function decimal($value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(trim($value))) ?: '';
    }

    protected function parseAddressComponents(?string $address, ?string $fallbackTown = null, ?string $fallbackProvince = null): array
    {
        $rawAddress = trim((string) $address);
        $segments = collect(preg_split('/,/', $rawAddress ?: '') ?: [])
            ->map(fn (string $segment) => trim($segment))
            ->filter()
            ->values();

        $geocoded = $rawAddress !== ''
            ? $this->addressGeocodingService->lookup($rawAddress, false)
            : null;

        $country = (string) ($geocoded['country'] ?? $this->extractCountry($segments) ?? 'Zambia');
        $province = (string) ($geocoded['province'] ?? $this->extractProvince($segments) ?? $this->sanitizeLocationFallback($fallbackProvince));
        $town = (string) ($geocoded['town'] ?? $this->extractTownOrDistrict($segments) ?? $this->sanitizeLocationFallback($fallbackTown));
        $district = (string) ($geocoded['district'] ?? $town);
        $province = $province !== '' ? $province : (string) ($this->inferProvinceFromTown($town) ?? '');
        $plotNumber = $this->extractPlotNumber($rawAddress);

        $lineSegments = $segments
            ->reject(fn (string $segment) => $this->isCountryToken($segment))
            ->reject(fn (string $segment) => $this->isProvinceToken($segment))
            ->values();

        $line1 = (string) ($lineSegments->get(0) ?? $rawAddress);
        $line2 = $lineSegments->slice(1)->implode(', ');

        $components = [
            'country' => $country,
            'province' => $province,
            'town' => $town,
            'district' => $district,
            'plot_number' => $plotNumber,
            'line_1' => $line1,
            'line_2' => $line2,
        ];

        return $components;
    }

    protected function extractCountry(Collection $segments): ?string
    {
        $country = $segments->first(fn (string $segment) => $this->isCountryToken($segment));

        return $country ? 'Zambia' : null;
    }

    protected function extractProvince(Collection $segments): ?string
    {
        $provinceMap = [
            'central' => 'Central',
            'copperbelt' => 'Copperbelt',
            'eastern' => 'Eastern',
            'luapula' => 'Luapula',
            'lusaka' => 'Lusaka',
            'muchinga' => 'Muchinga',
            'northern' => 'Northern',
            'north western' => 'North-Western',
            'northwestern' => 'North-Western',
            'southern' => 'Southern',
            'western' => 'Western',
        ];

        foreach ($segments as $segment) {
            $normalized = $this->normalizeName($segment);

            foreach ($provinceMap as $needle => $province) {
                if (str_contains($normalized, str_replace('-', ' ', $needle)) || $normalized === $needle) {
                    return $province;
                }
            }
        }

        return null;
    }

    protected function extractTownOrDistrict(Collection $segments): ?string
    {
        $knownPlaces = [
            'chililabombwe' => 'Chililabombwe',
            'chingola' => 'Chingola',
            'chipata' => 'Chipata',
            'choma' => 'Choma',
            'kabwe' => 'Kabwe',
            'kafue' => 'Kafue',
            'kalulushi' => 'Kalulushi',
            'kapiri mposhi' => 'Kapiri Mposhi',
            'kasama' => 'Kasama',
            'kitwe' => 'Kitwe',
            'livingstone' => 'Livingstone',
            'luanshya' => 'Luanshya',
            'lusaka' => 'Lusaka',
            'mansa' => 'Mansa',
            'mazabuka' => 'Mazabuka',
            'monze' => 'Monze',
            'mpika' => 'Mpika',
            'ndola' => 'Ndola',
            'petauke' => 'Petauke',
            'serenje' => 'Serenje',
            'solwezi' => 'Solwezi',
        ];

        foreach ($segments as $segment) {
            $normalized = $this->normalizeName($segment);

            foreach ($knownPlaces as $needle => $place) {
                if (str_contains($normalized, $needle)) {
                    return $place;
                }
            }
        }

        return null;
    }

    protected function inferProvinceFromTown(?string $town): ?string
    {
        $town = $this->normalizeName((string) $town);

        $map = [
            'chililabombwe' => 'Copperbelt',
            'chingola' => 'Copperbelt',
            'kalulushi' => 'Copperbelt',
            'kitwe' => 'Copperbelt',
            'luanshya' => 'Copperbelt',
            'mufulira' => 'Copperbelt',
            'ndola' => 'Copperbelt',
            'solwezi' => 'North-Western',
            'lusaka' => 'Lusaka',
            'kabwe' => 'Central',
            'kapiri mposhi' => 'Central',
            'serenje' => 'Central',
            'chipata' => 'Eastern',
            'petauke' => 'Eastern',
            'kasama' => 'Northern',
            'mpika' => 'Muchinga',
            'livingstone' => 'Southern',
            'mazabuka' => 'Southern',
            'monze' => 'Southern',
            'choma' => 'Southern',
            'mansa' => 'Luapula',
            'mongu' => 'Western',
            'kaoma' => 'Western',
            'senanga' => 'Western',
            'sesheke' => 'Western',
        ];

        return $map[$town] ?? null;
    }

    protected function extractPlotNumber(string $address): string
    {
        if (preg_match('/plot\s*(?:no\.?|number)?\s*([a-z0-9\-\/]+)/i', $address, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return '';
    }

    protected function isCountryToken(string $segment): bool
    {
        return str_contains($this->normalizeName($segment), 'zambia');
    }

    protected function isProvinceToken(string $segment): bool
    {
        return $this->extractProvince(collect([$segment])) !== null;
    }

    protected function sanitizeLocationFallback(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/\d/', $value)) {
            return '';
        }

        if (str_contains(Str::lower($value), 'house no') || str_contains(Str::lower($value), 'plot no')) {
            return '';
        }

        if (mb_strlen($value) > 40) {
            return '';
        }

        return $value;
    }

    protected function repaymentMapForLoans(Collection $loans, Carbon $asOfDate): array
    {
        $keys = $loans
            ->flatMap(fn (Loan $loan) => array_filter([(string) $loan->loan_id, (string) $loan->id]))
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        $grouped = Repayments::query()
            ->whereIn('loan_id', $keys->all())
            ->get()
            ->filter(function ($repayment) use ($asOfDate) {
                $date = $this->safeDate($repayment->receipt_date ?? $repayment->payment_date);

                return $date?->lte($asOfDate) ?? true;
            })
            ->groupBy(fn ($repayment) => (string) $repayment->loan_id);

        $map = [];

        foreach ($loans as $loan) {
            $combined = collect()
                ->merge($grouped->get((string) $loan->loan_id, collect()))
                ->merge($grouped->get((string) $loan->id, collect()))
                ->unique('id')
                ->values();

            $map[$this->repaymentMapKey($loan)] = $combined;
        }

        return $map;
    }

    protected function repaymentAmount(Repayments $repayment): float
    {
        if (filled($repayment->receipt_amount)) {
            return (float) $repayment->receipt_amount;
        }

        if (filled($repayment->payments)) {
            return (float) $repayment->payments;
        }

        return 0.0;
    }

    protected function repaymentMapKey(Loan $loan): string
    {
        return (string) ($loan->id ?? $loan->loan_id);
    }
}
