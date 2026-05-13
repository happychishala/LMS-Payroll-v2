<x-filament::page>
    <form wire:submit.prevent="export" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="startDate" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Disbursement Start Date
                </label>
                <input
                    type="date"
                    id="startDate"
                    wire:model="startDate"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                />
            </div>

            <div>
                <label for="endDate" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Disbursement End Date
                </label>
                <input
                    type="date"
                    id="endDate"
                    wire:model="endDate"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                />
            </div>

            <div>
                <label for="settlementStart" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Settlement Start Date
                </label>
                <input
                    type="date"
                    id="settlementStart"
                    wire:model="settlementStart"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                />
            </div>

            <div>
                <label for="settlementEnd" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Settlement End Date
                </label>
                <input
                    type="date"
                    id="settlementEnd"
                    wire:model="settlementEnd"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-filament::button type="button" wire:click="preview" color="gray">
                Preview
            </x-filament::button>

            <x-filament::button type="submit">
                Export to CSV
            </x-filament::button>
        </div>
    </form>

    <hr class="my-6"/>

    <h2 class="text-lg font-semibold">Preview Settled Loans</h2>

    @php
        // normalize to a Collection for the preview table
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
                    <th class="px-3 py-2">Settlement Date</th>
                    <th class="px-3 py-2">Disbursed Amount</th>
                    <th class="px-3 py-2">Balance</th>
                    <th class="px-3 py-2">Employer</th>
                    <th class="px-3 py-2">Preview</th> <!-- New column -->
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
                        <td class="px-3 py-2">{{ optional($loan->updated_at)?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->disbursement_amount ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ number_format($loan->balance ?? 0, 2) }}</td>
                        <td class="px-3 py-2">{{ $loan->employer }}</td>
                        <td class="px-3 py-2">
                            <a
                                href="{{ route('filament.admin.resources.loans.view', ['record' => $loan->id]) }}"
                                target="_blank"
                                class="inline-flex items-center px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded text-xs font-semibold"
                            >
                                Preview
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-6 text-center text-gray-500">
                            No preview loaded. Choose filters and click Preview.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::page>
