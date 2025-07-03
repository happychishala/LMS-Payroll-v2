{{-- resources/views/filament/resources/wallet-resource/pages/amortization.blade.php --}}
<x-filament-panels::page
    class="text-gray-900 dark:text-gray-900"
>
    {{ $this->form }}

    @if(count($this->schedule))
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Period</th>
                        <th>Date</th>
                        <th class="text-right">Payment</th>
                        <th class="text-right">Interest</th>
                        <th class="text-right">Principal</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($this->schedule as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['period'] }}</td>
                            <td class="px-4 py-2">{{ $row['date'] }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['payment'], 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['interest'], 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['principal'], 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
