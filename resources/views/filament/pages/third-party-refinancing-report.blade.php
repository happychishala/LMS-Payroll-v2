<x-filament::page>
    {{-- FILTERS + EXPORT --}}
    <form wire:submit.prevent="export" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Disbursement Start</label>
                <input type="date" wire:model="startDate"
                       class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Disbursement End</label>
                <input type="date" wire:model="endDate"
                       class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" />
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Third-Party Name</label>
                <select wire:model="thirdPartyName"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    @foreach ($this->thirdPartyNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Recency (DIA-based)</label>
                <select wire:model="recency"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm">
                    <option value="">All</option>
                    <option>Pass (0 days)</option>
                    <option>Pass (1 to 59)</option>
                    <option>Special Mention (60 to 89)</option>
                    <option>Substandard (1) (90 to 119)</option>
                    <option>Substandard (2) (120 to 179)</option>
                    <option>Doubtful (1) (180 to 269)</option>
                    <option>Doubtful (2) (270 to 364)</option>
                    <option>Loss (365+)</option>
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

    {{-- PERFORMANCE SNAPSHOT --}}
    <hr class="my-6"/>

    <h2 class="text-lg font-semibold mb-3">Performance Snapshot</h2>

    @php
        /** @var array $snap */
        $snap = $this->snapshot ?? [];
    @endphp

    @if (!empty($snap))
        {{-- Totals --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Loans</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['loans'] ?? 0) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Total 3rd-Party Paid</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['third_party'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Net to Borrower</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['net_to_borrower'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Total Disbursed</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['disbursed'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded border p-3">
                <div class="text-xs text-gray-500">Total VIA</div>
                <div class="text-xl font-semibold">{{ number_format($snap['totals']['via'] ?? 0, 2) }}</div>
            </div>
        </div>

        {{-- Breakdown tables --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="rounded border overflow-hidden">
                <div class="px-3 py-2 font-semibold bg-gray-50 dark:bg-gray-800">Recency Breakdown</div>
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

            <div class="rounded border overflow-hidden">
                <div class="px-3 py-2 font-semibold bg-gray-50 dark:bg-gray-800">DIA Buckets</div>
                <table class="min-w-full text-sm">
                    <tbody class="bg-white dark:bg-gray-900">
                        <tr class="border-t">
                            <td class="px-3 py-2">0</td>
                            <td class="px-3 py-2 text-right">{{ number_format($snap['dia']['0'] ?? 0) }}</td>
                        </tr>
                        <tr class="border-t">
                            <td class="px-3 py-2">30</td>
                            <td class="px-3 py-2 text-right">{{ number_format($snap['dia']['30'] ?? 0) }}</td>
                        </tr>
                        <tr class="border-t">
                            <td class="px-3 py-2">60</td>
                            <td class="px-3 py-2 text-right">{{ number_format($snap['dia']['60'] ?? 0) }}</td>
                        </tr>
                        <tr class="border-t">
                            <td class="px-3 py-2">90+</td>
                            <td class="px-3 py-2 text-right">{{ number_format($snap['dia']['90+'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500">No data for current filters.</p>
    @endif

    {{-- PREVIEW (first 20 rows) --}}
    <hr class="my-6"/>

    <h2 class="text-lg font-semibold mb-2">Preview (first 20 rows)</h2>

    @php
        /** @var \Illuminate\Support\Collection|array $rows */
        $rows = collect($this->previewRows ?? []);
        $first = $rows->first() ?? [];
    @endphp

    @if ($rows->isNotEmpty())
        <div class="mt-2 overflow-auto rounded border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        @foreach (array_keys($first) as $heading)
                            <th class="px-3 py-2">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    @foreach ($rows as $r)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            @foreach ($r as $val)
                                <td class="px-3 py-2">
                                    {{ is_numeric($val) ? number_format($val, 2) : $val }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">No records to preview. Adjust filters above.</p>
    @endif
</x-filament::page>
