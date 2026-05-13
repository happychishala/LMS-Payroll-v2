<x-filament::page>
    {{-- FILTERS + EXPORT --}}
    <form wire:submit.prevent="export" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="offPayrollOnly"
                           class="rounded border-gray-300 dark:border-gray-600" />
                    Off‑Payroll only
                </label>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Search (name / NRC / phone)</label>
                <input type="text" wire:model.debounce.500ms="search"
                       placeholder="e.g. 123456/11/1 or Chanda"
                       class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm text-sm" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-semibold text-sm">
                Export CSV
            </button>
        </div>
    </form>

    {{-- PREVIEW (first 25 rows) --}}
    <hr class="my-6"/>
    <h2 class="text-lg font-semibold mb-2">Preview (first 25 rows)</h2>

    @php
        $rows = $this->previewRows ?? collect();
        $rows = $rows instanceof \Illuminate\Support\Collection ? $rows : collect($rows);
        $rows = $rows->values();
        $headers = array_keys($rows->first() ?? []);
    @endphp

    @if ($rows->isNotEmpty())
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
                    @foreach ($rows as $r)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            @foreach ($r as $val)
                                <td class="px-3 py-2">{{ is_numeric($val) ? number_format($val, 2) : $val }}</td>
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
