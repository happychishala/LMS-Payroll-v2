<x-filament::page>
    <form wire:submit.prevent="export" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Loan Name</label>
                <select wire:model="loanName" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    @foreach ($this->loanNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Employer</label>
                <select wire:model="employer" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    @foreach ($this->employers as $emp)
                        <option value="{{ $emp }}">{{ $emp }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Month (YYYY-MM)</label>
                <input type="month" wire:model="month" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" />
            </div>

            <div></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Disbursement Start Date</label>
                <input type="date" wire:model="startDate" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Disbursement End Date</label>
                <input type="date" wire:model="endDate" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" />
            </div>

            <div class="md:col-span-2 flex items-end gap-3">
                <x-filament::button type="button" wire:click="preview" color="gray">
                    Preview
                </x-filament::button>

                <x-filament::button type="submit">
                    Export to CSV
                </x-filament::button>
            </div>
        </div>

        @error('month')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </form>

    <hr class="my-6"/>

    <h2 class="text-lg font-semibold">Preview (new loans)</h2>

    @php
        $rows = collect($previewRows ?? []);
    @endphp

    <div class="mt-4 overflow-auto rounded border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2">Loan ID</th>
                    <th class="px-3 py-2">Borrower Name</th>
                    <th class="px-3 py-2">Loan Name</th>
                    <th class="px-3 py-2">Loan Category</th>
                    <th class="px-3 py-2">Disbursement Date</th>
                    <th class="px-3 py-2">Principal Amount</th>
                    <th class="px-3 py-2">Disbursed Amount</th>
                    <th class="px-3 py-2">Third Party Name</th>
                    <th class="px-3 py-2">Third Party Balance</th>
                    <th class="px-3 py-2">Admin Fee</th>
                    <th class="px-3 py-2">Insurance Fee</th>
                    <th class="px-3 py-2">Arrangement Fee</th>
                    <th class="px-3 py-2">Total Fees Charged</th>
                    <th class="px-3 py-2">Monthly Instalment (Total)</th>
                    <th class="px-3 py-2">Instalment (Excl. Insurance)</th>
                    <th class="px-3 py-2">Monthly Insurance</th>
                    <th class="px-3 py-2">Employer</th>
                    <th class="px-3 py-2">Employee Number</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Interest Rate</th>
                    <th class="px-3 py-2">Term (Months)</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900">
                @forelse ($rows as $loan)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $loan->loan_id }}</td>
                        <td class="px-3 py-2">
                            {{ optional($loan->borrower)->full_name ?? trim(($loan->other_names ?? '') . ' ' . ($loan->last_name ?? '')) }}
                        </td>
                        <td class="px-3 py-2">{{ optional($loan->loan_type)->loan_name }}</td>
                        <td class="px-3 py-2">{{ $loan->loan_category }}</td>
                        <td class="px-3 py-2">{{ optional($loan->loan_release_date)?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->principal_amount ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->disbursement_amount ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ $loan->third_party_name ?? 'N/A' }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->total_third_party_balance ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->admin_fee ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->insurance_fee ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->arrangement_fee ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format(($loan->admin_fee ?? 0) + ($loan->insurance_fee ?? 0) + ($loan->arrangement_fee ?? 0), 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->total_monthly_repayment ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->repayment_amount ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->monthly_insurance ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ $loan->employer }}</td>
                        <td class="px-3 py-2">{{ $loan->employee_no }}</td>
                        <td class="px-3 py-2">{{ $loan->loan_status }}</td>
                        <td class="px-3 py-2">{{ $loan->interest_rate }}</td>
                        <td class="px-3 py-2">{{ $loan->term_months ?? $loan->loan_duration }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="21" class="px-3 py-6 text-center text-gray-500">
                            No preview loaded. Choose filters and click Preview.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</x-filament::page>
