<x-filament::page>
    <form wire:submit.prevent="export" class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Report Date</label>
                <input
                    type="date"
                    wire:model="reportDate"
                    required
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Employer</label>
                <select
                    wire:model="employer"
                    class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="">All</option>
                    @foreach ($this->employers as $employer)
                        <option value="{{ $employer }}">{{ $employer }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                >
                    Download XLSX
                </button>
            </div>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input
                type="checkbox"
                wire:model="includeRecordsWithIssues"
                class="rounded border-gray-300 dark:border-gray-600"
            />
            Include loans with validation issues in export
        </label>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            The export follows the sample workbook's <strong>Final</strong> sheet. Before export, each loan is checked against customer details and key CRB-required fields.
        </p>
    </form>

    <hr class="my-6" />

    @php($summary = $this->summary)

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="text-xs uppercase text-gray-500">Loans Checked</div>
            <div class="mt-1 text-2xl font-semibold">{{ $summary['total_loans'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-green-200 p-4 dark:border-green-800">
            <div class="text-xs uppercase text-gray-500">Valid Loans</div>
            <div class="mt-1 text-2xl font-semibold text-green-700 dark:text-green-400">{{ $summary['valid_loans'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-red-200 p-4 dark:border-red-800">
            <div class="text-xs uppercase text-gray-500">Blocked Loans</div>
            <div class="mt-1 text-2xl font-semibold text-red-700 dark:text-red-400">{{ $summary['blocked_loans'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-red-200 p-4 dark:border-red-800">
            <div class="text-xs uppercase text-gray-500">Errors</div>
            <div class="mt-1 text-2xl font-semibold text-red-700 dark:text-red-400">{{ $summary['error_count'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 p-4 dark:border-amber-800">
            <div class="text-xs uppercase text-gray-500">Warnings</div>
            <div class="mt-1 text-2xl font-semibold text-amber-700 dark:text-amber-400">{{ $summary['warning_count'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-blue-200 p-4 dark:border-blue-800">
            <div class="text-xs uppercase text-gray-500">Rows To Export</div>
            <div class="mt-1 text-2xl font-semibold text-blue-700 dark:text-blue-400">{{ $summary['exportable_rows'] ?? 0 }}</div>
        </div>
    </div>

    <hr class="my-6" />

    <h2 class="mb-2 text-lg font-semibold">Validation Issues</h2>

    @php($issues = collect($this->issueRows ?? []))

    @if ($issues->isNotEmpty())
        <div class="overflow-auto rounded border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Loan ID</th>
                        <th class="px-3 py-2">Borrower</th>
                        <th class="px-3 py-2">Severity</th>
                        <th class="px-3 py-2">Field</th>
                        <th class="px-3 py-2">Message</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    @foreach ($issues as $issue)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-3 py-2">{{ $issue['loan_id'] }}</td>
                            <td class="px-3 py-2">{{ $issue['borrower_name'] }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $issue['severity'] === 'error',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $issue['severity'] === 'warning',
                                ])>
                                    {{ strtoupper($issue['severity']) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $issue['field'] }}</td>
                            <td class="px-3 py-2">{{ $issue['message'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">No validation issues found for the current filter.</p>
    @endif

    <hr class="my-6" />

    <h2 class="mb-2 text-lg font-semibold">Preview (first 20 rows)</h2>

    @php($previewRows = collect($this->previewRows ?? []))
    @php($headers = array_keys($previewRows->first() ?? []))

    @if ($previewRows->isNotEmpty())
        <div class="overflow-auto rounded border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        @foreach ($headers as $heading)
                            <th class="px-3 py-2">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    @foreach ($previewRows as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            @foreach ($headers as $heading)
                                <td class="px-3 py-2">{{ $row[$heading] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">No loans match the selected filters.</p>
    @endif
</x-filament::page>
