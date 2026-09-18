<x-filament::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">As of Date</label>
                <input
                    type="date"
                    wire:model.live="asOfDate"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Employer</label>
                <select
                    wire:model.live="employer"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="">All</option>
                    @foreach ($this->employers as $employer)
                        <option value="{{ $employer }}">{{ $employer }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Loan Name</label>
                <select
                    wire:model.live="loanName"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="">All</option>
                    @foreach ($this->loanNames as $loanName)
                        <option value="{{ $loanName }}">{{ $loanName }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                <select
                    wire:model.live="status"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="">All</option>
                    @foreach ($this->statuses as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Loan ID</label>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="loanId"
                    placeholder="e.g. L001"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Repayment Month</label>
                <select
                    wire:model.live="filterMonth"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="">All Months</option>
                    @foreach ([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Repayment Year</label>
                <select
                    wire:model.live="filterYear"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="">All Years</option>
                    @foreach ($this->availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="export"
                class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
            >
                Download CSV
            </button>
        </div>

        @php
            $summary = $this->summary;
            $rows = $this->previewRows;
        @endphp

        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <div class="rounded border p-4">
                <div class="text-xs text-gray-500">Loans</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['loans'] ?? 0) }}</div>
            </div>
            <div class="rounded border p-4">
                <div class="text-xs text-gray-500">Accrued Interest</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['accrued_interest'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-4">
                <div class="text-xs text-gray-500">Interest Paid</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['interest_paid'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-4">
                <div class="text-xs text-gray-500">Unpaid Accrued Interest</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['unpaid_accrued_interest'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-4">
                <div class="text-xs text-gray-500">Total Contracted Interest</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['contracted_interest'] ?? 0, 2) }}</div>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-semibold">Preview</h2>
            <p class="text-sm text-gray-500">First 50 loans matching the current filters.</p>
        </div>

        @php
            $headers = array_keys($rows->first() ?? []);
            $twoDecimalColumns = [
                'Monthly Expected Interest',
                'Accrued Interest',
                'Interest Paid',
                'Unpaid Accrued Interest',
                'Total Contracted Interest',
                'Balance',
            ];
        @endphp

        @if ($rows->isNotEmpty())
            <div class="overflow-auto rounded border border-gray-200 dark:border-gray-700">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            @foreach ($headers as $header)
                                <th class="px-3 py-2">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900">
                        @foreach ($rows as $row)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                @foreach ($row as $key => $value)
                                    <td class="px-3 py-2">
                                        {{ is_numeric($value) && in_array($key, $twoDecimalColumns, true) ? number_format($value, 2) : $value }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No loans match the current filters.</p>
        @endif
    </div>
</x-filament::page>
