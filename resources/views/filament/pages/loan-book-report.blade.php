<x-filament::page>
    {{-- FILTERS + EXPORT --}}
    <form wire:submit.prevent="export" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">As of Date</label>
                <input type="date" wire:model="asOfDate"
                       class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" required />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Employer</label>
                <select wire:model="employer"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    @foreach ($this->employers as $emp)
                        <option value="{{ $emp }}">{{ $emp }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Loan Name</label>
                <select wire:model="loanName"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    @foreach ($this->loanNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                <select wire:model="status"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    @foreach ($this->statuses as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-semibold text-sm">
                Export CSV
            </button>
        </div>
    </form>

    {{-- SNAPSHOT HEADER --}}
    <hr class="my-6"/>

    <h2 class="text-lg font-semibold mb-3">
        Snapshot (as of {{ \Illuminate\Support\Carbon::parse($asOfDate)->format('Y-m-d') }})
    </h2>

    @php
        /** @var array $snap */
        $snap = $this->snapshot ?? [];
    @endphp

    @if(!empty($snap))
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Loans</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['loans'] ?? 0) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Principal Outstanding</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['principal'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Principal + Interest</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['principal_interest'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Current Recoverable</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['current_recoverable'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Total VIA</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['via'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Avg DIA (days)</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['avg_dia'] ?? 0, 1) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Avg Remaining Term (mo)</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['avg_remaining_term'] ?? 0, 1) }}</div>
            </div>
        </div>

        {{-- Recency Breakdown --}}
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mt-6">
            <div class="rounded border overflow-hidden">
                <div class="px-3 py-2 font-semibold bg-gray-50 dark:bg-gray-800">Recency Breakdown (DIA-based)</div>
                <table class="min-w-full text-sm">
                    <tbody class="bg-white dark:bg-gray-900">
                        @foreach ([
                            'Pass (0 days)',
                            'Pass (1 to 59)',
                            'Special Mention (60 to 89)',
                            'Substandard (1) (90 to 119)',
                            'Substandard (2) (120 to 179)',
                            'Doubtful (1) (180 to 269)',
                            'Doubtful (2) (270 to 364)',
                            'Loss (365+)',
                        ] as $label)
                            <tr class="border-t">
                                <td class="px-3 py-2">{{ $label }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($snap['recency'][$label] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500">No data for current filters.</p>
    @endif

    {{-- PREVIEW (first 25 rows) --}}
    <hr class="my-6"/>

    <h2 class="text-lg font-semibold mb-2">Preview (first 25 rows)</h2>

    @php
        // Force a Collection, then normalize keys for header rendering.
        $items = $this->previewRows ?? collect();
        $items = $items instanceof \Illuminate\Support\Collection ? $items : collect($items);
        $items = $items->values();
        $headers = array_keys($items->first() ?? []);
        $twoDecimalColumns = [
            'Monthly Instalment',
            'Principal Outstanding',
            'Principal + Interest (GRZ)',
            'Current Total Recoverable',
            'DIA',
            'VIA',
        ];
    @endphp

    @if ($items->isNotEmpty())
        <div class="mt-2 overflow-auto rounded border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        @foreach ($headers as $heading)
                            <th class="px-3 py-2">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    @foreach ($items as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            @foreach ($row as $heading => $val)
                                <td class="px-3 py-2">
                                    {{ is_numeric($val) && in_array($heading, $twoDecimalColumns, true) ? number_format($val, 2) : $val }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">No records match current filters.</p>
    @endif
</x-filament::page>
