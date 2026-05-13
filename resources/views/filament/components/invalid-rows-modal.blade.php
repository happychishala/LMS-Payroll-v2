<x-filament::page>
    @php $invalids = session('invalid_rows', []); @endphp

    <div
        x-data="{ open: {{ empty($invalids) ? 'false' : 'false' }}, rows: @json($invalids) }"
        x-cloak
        x-on:open-invalid-rows-modal.window="open = true"
        class="fixed inset-0 z-50 flex items-start justify-center p-6"
    >
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>

        <div class="relative bg-white rounded-lg shadow-lg max-w-6xl w-full max-h-[80vh] overflow-auto z-10">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-semibold">Review Invalid Rows</h3>
                <button type="button" class="text-gray-500" @click="open = false">×</button>
            </div>

            <div class="p-4">
                <template x-if="rows.length === 0">
                    <div class="text-sm text-gray-600">No invalid rows to review.</div>
                </template>

                <template x-if="rows.length > 0">
                    <form method="POST" action="{{ route('repayments.import.corrections') }}">
                        @csrf
                        <div class="overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="p-2 text-left">Row</th>
                                        <th class="p-2 text-left">Employee No</th>
                                        <th class="p-2 text-left">Name</th>
                                        <th class="p-2 text-left">Amount</th>
                                        <th class="p-2 text-left">Receipt Date</th>
                                        <th class="p-2 text-left">Errors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invalids as $i => $row)
                                        <tr class="border-t">
                                            <td class="p-2">{{ $row['row_number'] }}</td>
                                            <td class="p-2">
                                                <input name="invalid[{{ $i }}][employee_no]" value="{{ old("invalid.$i.employee_no", $row['employee_no']) }}" class="w-full border px-2 py-1 rounded" />
                                            </td>
                                            <td class="p-2">
                                                <input name="invalid[{{ $i }}][name]" value="{{ old("invalid.$i.name", $row['name']) }}" class="w-full border px-2 py-1 rounded" />
                                            </td>
                                            <td class="p-2">
                                                <input name="invalid[{{ $i }}][amount]" value="{{ old("invalid.$i.amount", $row['amount']) }}" class="w-full border px-2 py-1 rounded" />
                                            </td>
                                            <td class="p-2">
                                                <input name="invalid[{{ $i }}][receipt_date]" value="{{ old("invalid.$i.receipt_date", $row['receipt_date']) }}" placeholder="dd/mm/yyyy" class="w-full border px-2 py-1 rounded" />
                                            </td>
                                            <td class="p-2 text-xs text-red-600">{{ $row['errors'] }}</td>
                                            <input type="hidden" name="invalid[{{ $i }}][row_number]" value="{{ $row['row_number'] }}">
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex justify-end gap-2 mt-4">
                            <button type="button" class="px-4 py-2 bg-gray-200 rounded" @click="open = false">Close</button>
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded">Save corrections & import</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    <script>
        // wire -> window event bridge
        Livewire.on('openInvalidRowsModal', payload => {
            window.dispatchEvent(new Event('open-invalid-rows-modal'));
        });

        // support opening from the summary button (which calls Livewire.emit)
        window.addEventListener('open-invalid-rows-modal', () => {
            // no-op here; Alpine x-on handles toggling open via the Livewire event above
        });
    </script>
</x-filament::page>