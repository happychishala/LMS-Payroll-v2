<x-filament-panels::page>
    @php
        $loan = $this->selectedLoan;
        $summary = $this->scheduleSummary;
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Loan Amortization</x-slot>
            <x-slot name="description">
                Select a loan to load its stored repayment terms and generate the amortization schedule from the actual loan record.
            </x-slot>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
                <div class="space-y-4">
                    <div>
                        <label for="loan_id" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">Loan</label>
                        <select
                            id="loan_id"
                            wire:model.live="loan_id"
                            class="block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select a loan</option>
                            @foreach ($loanOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Principal</div>
                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($principal, 2) }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Rate</div>
                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($rate, 2) }}%</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Monthly Insurance</div>
                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($monthlyInsurance, 2) }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Term</div>
                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($term) }} months</div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                        <div><span class="font-medium text-gray-950 dark:text-white">Schedule start:</span> {{ $startDate ?: '-' }}</div>
                        <div class="mt-1"><span class="font-medium text-gray-950 dark:text-white">Stored monthly repayment:</span> {{ number_format($totalMonthlyRepayment, 2) }}</div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-filament::button wire:click="calculateSchedule" icon="heroicon-m-calculator">
                            Refresh Schedule
                        </x-filament::button>

                        @if ($this->schedule !== [])
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format(count($this->schedule)) }} installments loaded
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Borrower</div>
                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $loan ? (trim(collect([$loan->borrower?->first_name ?: $loan->borrower?->other_names, $loan->borrower?->last_name])->filter()->implode(' ')) ?: 'N/A') : '-' }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $loan?->borrower?->customer_id ?: 'No borrower selected' }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Loan Reference</div>
                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $loan?->loan_number ?: ($loan?->loan_id ?: '-') }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $loan?->loan_type?->loan_name ?: ($loan?->loan_category ?: 'No loan selected') }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Release Date</div>
                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $loan?->loan_release_date ? \Illuminate\Support\Carbon::parse($loan->loan_release_date)->format('Y-m-d') : '-' }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">First repayment {{ $startDate ?: '-' }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Stored Instalment</div>
                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($totalMonthlyRepayment, 2) }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monthly insurance {{ number_format($monthlyInsurance, 2) }}</div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        @if ($this->schedule !== [])
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Installments</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['installments']) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Payment</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_payment'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Principal</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_principal'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Interest</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_interest'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Insurance</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_insurance'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Ending Balance</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['ending_balance'], 2) }}</div>
                </x-filament::section>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-2">
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Expected Total Recoverable</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['expected_total_recoverable'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Actual Total Recoverable</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_total_recoverable'], 2) }}</div>
                </x-filament::section>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Actual Paid</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_total_paid'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Actual Principal Paid</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_principal_paid'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Actual Interest Paid</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_interest_paid'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Actual Insurance Paid</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_insurance_paid'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Interest Arrears</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_interest_arrears'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Insurance Arrears</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_insurance_arrears'], 2) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Actual Balance</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['actual_ending_balance'], 2) }}</div>
                </x-filament::section>
            </div>

            <x-filament::section>
                <x-slot name="heading">Amortization Schedule</x-slot>
                <x-slot name="description">
                    Page {{ $this->schedulePage }} of {{ $this->schedulePageCount }}. Actual repayment columns reflect recorded repayments for each installment period.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3">Payment Date</th>
                                <th class="px-4 py-3 text-right">Payment</th>
                                <th class="px-4 py-3 text-right">Principal</th>
                                <th class="px-4 py-3 text-right">Interest</th>
                                <th class="px-4 py-3 text-right">Insurance</th>
                                <th class="px-4 py-3 text-right">Sanlam</th>
                                <th class="px-4 py-3 text-right">ZedFin</th>
                                <th class="px-4 py-3 text-right">Scheduled Balance</th>
                                <th class="px-4 py-3 text-right">Actual Paid</th>
                                <th class="px-4 py-3 text-right">Actual Principal</th>
                                <th class="px-4 py-3 text-right">Actual Interest</th>
                                <th class="px-4 py-3 text-right">Actual Insurance</th>
                                <th class="px-4 py-3 text-right">Interest Arrears</th>
                                <th class="px-4 py-3 text-right">Insurance Arrears</th>
                                <th class="px-4 py-3 text-right">Expected Recoverable</th>
                                <th class="px-4 py-3 text-right">Actual Recoverable</th>
                                <th class="px-4 py-3 text-right">Actual Balance</th>
                                <th class="px-4 py-3 text-right">Variance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->paginatedSchedule as $row)
                                <tr class="bg-white dark:bg-transparent">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['period'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['date'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['payment'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['principal'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['interest'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['insurance'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['sanlam'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['zedfin'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-950 dark:text-white">{{ number_format($row['balance'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['actual_payment'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['actual_principal_paid'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['actual_interest_paid'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['actual_insurance_paid'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['interest_arrears'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['insurance_arrears'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['expected_total_recoverable'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ number_format($row['actual_total_recoverable'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-950 dark:text-white">{{ number_format($row['actual_balance'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-right {{ ($row['balance_variance'] ?? 0) > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                        {{ number_format($row['balance_variance'] ?? 0, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($this->schedulePageCount > 1)
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <x-filament::button
                            wire:click="gotoSchedulePage({{ max(1, $this->schedulePage - 1) }})"
                            color="gray"
                            size="sm"
                            :disabled="$this->schedulePage === 1"
                        >
                            Previous
                        </x-filament::button>

                        @for ($page = 1; $page <= $this->schedulePageCount; $page++)
                            <x-filament::button
                                wire:click="gotoSchedulePage({{ $page }})"
                                :color="$this->schedulePage === $page ? 'primary' : 'gray'"
                                size="sm"
                            >
                                {{ $page }}
                            </x-filament::button>
                        @endfor

                        <x-filament::button
                            wire:click="gotoSchedulePage({{ min($this->schedulePageCount, $this->schedulePage + 1) }})"
                            color="gray"
                            size="sm"
                            :disabled="$this->schedulePage === $this->schedulePageCount"
                        >
                            Next
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">No Schedule Yet</x-slot>
                <x-slot name="description">
                    Select a loan to auto-generate the amortization schedule, or use Refresh Schedule after changing the loan.
                </x-slot>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
