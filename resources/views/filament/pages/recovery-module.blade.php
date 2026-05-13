<x-filament::page>
    <div class="space-y-6">

        {{-- FILTER SECTION --}}
        <div class="flex flex-wrap items-end gap-4">
            {{-- Month --}}
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Select Month</label>
                <select wire:model="selectedMonth" class="form-select w-full rounded-lg shadow-sm dark:bg-gray-800 dark:text-white dark:border-gray-600">
                    @foreach(range(1, 12) as $month)
                        <option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->format('F') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Year --}}
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Select Year</label>
                <select wire:model="selectedYear" class="form-select w-full rounded-lg shadow-sm dark:bg-gray-800 dark:text-white dark:border-gray-600">
                    @foreach(range(2023, now()->year + 1) as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Loan Type --}}
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Loan Type</label>
                <select wire:model="selectedLoanType" class="form-select w-full rounded-lg shadow-sm dark:bg-gray-800 dark:text-white dark:border-gray-600">
                    <option value="">All</option>
                    @foreach($loanTypes as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Filter --}}
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select wire:model="selectedStatus" class="form-select w-full rounded-lg shadow-sm dark:bg-gray-800 dark:text-white dark:border-gray-600">
                    <option value="All">All</option>
                    <option value="Performing">Performing</option>
                    <option value="Underpaid">Underpaid</option>
                    <option value="Missed">Missed</option>
                    <option value="Settled">Settled</option>
                </select>
            </div>

            {{-- Apply Filter --}}
            {{-- Apply Filter --}}
<div class="pt-5">
    <x-filament::button
        color="primary"
        wire:click="applyFilter"
    >
        Apply
    </x-filament::button>
</div>

{{-- Export Buttons --}}
<div class="pt-5">
    <div class="flex gap-2">
        <x-filament::button
            color="success"
            wire:click="exportData('excel')"
        >
            Export Excel
        </x-filament::button>

        <x-filament::button
            color="danger"
            wire:click="exportData('pdf')"
        >
            Export PDF
        </x-filament::button>

        <x-filament::button
            color="warning"
            wire:click="exportData('word')"
        >
            Export Word
        </x-filament::button>
    </div>
</div>

        </div>

        {{-- DASHBOARD SUMMARY CARDS (2x2 Grid) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
            @foreach ([
                ['label' => 'Performing Loans', 'value' => $summaryStats['performing']],
                ['label' => 'Missed Loans', 'value' => $summaryStats['missed']],
                ['label' => 'Amount Collected', 'value' => 'ZMW ' . number_format($summaryStats['amount_collected'], 2)],
                ['label' => 'Amount Missed', 'value' => 'ZMW ' . number_format($summaryStats['amount_missed'], 2)],
            ] as $stat)
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                    <p class="text-xl font-bold text-orange-600 dark:text-orange-400">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- TABLE --}}
        <div class="overflow-x-auto bg-white dark:bg-gray-900 rounded-lg shadow border dark:border-gray-700">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Loan ID</th>
                        <th class="px-4 py-3">Borrower ID</th>
                        <th class="px-4 py-3">Borrower Name</th>
                        <th class="px-4 py-3">Loan Type</th>
                        <th class="px-4 py-3">Release Date</th>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3">Due Date</th>
                        <th class="px-4 py-3">Expected</th>
                        <th class="px-4 py-3">Paid</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 dark:text-gray-200">
                    @forelse($this->paginatedData as $record)
                        <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-2">{{ $record['loan_id'] }}</td>
                            <td class="px-4 py-2">{{ $record['borrower_id'] }}</td>
                            <td class="px-4 py-2">{{ $record['borrower_name'] }}</td>
                            <td class="px-4 py-2">{{ $record['loan_name'] }}</td>
                            <td class="px-4 py-2">{{ \Carbon\Carbon::parse($record['loan_release_date'])->format('M d, Y') }}</td>
                            <td class="px-4 py-2">{{ $record['month'] }}</td>
                            <td class="px-4 py-2">{{ $record['due_date'] }}</td>
                            <td class="px-4 py-2">ZMW {{ number_format($record['expected'], 2) }}</td>
                            <td class="px-4 py-2">ZMW {{ number_format($record['paid'], 2) }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $statusColors = [
                                        'Performing' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-white',
                                        'Underpaid' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-600 dark:text-white',
                                        'Missed' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-white',
                                        'Settled' => 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-white',
                                    ];
                                @endphp
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColors[$record['status']] ?? '' }}">
                                    {{ $record['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-4 text-center text-gray-400 dark:text-gray-500">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        @if (count($recoveryData) > $perPage)
            <div class="mt-4 flex justify-center gap-2">
                @php
                    $totalPages = ceil(count($recoveryData) / $perPage);
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end = min($totalPages, $page + $range);
                @endphp

                @if ($page > 1)
                    <button wire:click="goToPage({{ $page - 1 }})"
                            class="px-3 py-1 rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-white">Prev</button>
                @endif

                @for ($i = $start; $i <= $end; $i++)
                    <button
                        wire:click="goToPage({{ $i }})"
                        class="px-3 py-1 rounded {{ $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' }}"
                    >
                        {{ $i }}
                    </button>
                @endfor

                @if ($page < $totalPages)
                    <button wire:click="goToPage({{ $page + 1 }})"
                            class="px-3 py-1 rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-white">Next</button>
                @endif
            </div>
        @endif
    </div>
</x-filament::page>
