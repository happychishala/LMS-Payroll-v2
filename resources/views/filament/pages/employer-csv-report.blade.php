<x-filament::page>
    <form wire:submit.prevent="export" class="space-y-6">
        <div class="space-y-2">
            <label class="block text-sm font-medium">Loan Name</label>
            <select wire:model="loanName" required class="w-full rounded-md border-gray-300 shadow-sm">
                <option value="">Select Loan Name</option>
                @foreach ($this->loanNames as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-2">
            <label class="block text-sm font-medium">Instalment Month (required)</label>
            <input type="month" wire:model="month" required class="w-full rounded-md border-gray-300 shadow-sm" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Disbursement Start Date</label>
                <input type="date" wire:model="startDate" class="w-full rounded-md border-gray-300 shadow-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium">Disbursement End Date</label>
                <input type="date" wire:model="endDate" class="w-full rounded-md border-gray-300 shadow-sm" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-filament::button type="button" wire:click="preview" color="gray">
                Preview
            </x-filament::button>

            <x-filament::button type="submit">
                Download CSV
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8">
        <h3 class="text-lg font-semibold mb-2">Preview (first 20 rows)</h3>
        @if (count($previewRows))
            <div class="overflow-auto rounded border">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            @foreach (array_keys($previewRows[0]) as $heading)
                                <th class="px-3 py-2">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr class="border-t">
                                @foreach ($row as $val)
                                    <td class="px-3 py-1">{{ is_numeric($val) ? number_format((float) $val, 2) : $val }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No preview loaded. Choose filters and click Preview.</p>
        @endif
    </div>
</x-filament::page>
