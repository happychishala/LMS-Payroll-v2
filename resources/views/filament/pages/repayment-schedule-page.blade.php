<x-filament::page>
    <form wire:submit.prevent="generate">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Generate
            </x-filament::button>
        </div>
    </form>

    @if($schedule->isNotEmpty())
        <div class="mt-6">
            <x-filament::button wire:click="exportToExcel">
                Export to Excel
            </x-filament::button>
<br><hr><br>
            <table class="min-w-full mt-4 border text-xs">
                <thead>
                    <tr>
                        @foreach(array_keys($schedule->first()) as $column)
                            <th class="border px-2 py-1">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule as $row)
                        <tr>
                            @foreach($row as $value)
                                <td class="border px-2 py-1">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::page>
